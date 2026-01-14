<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\TenantSession;
use App\Models\Event;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;        
    public int $backoff = 5;      
    public int $timeout = 120;    
    public array $message;

    public function __construct(array $message)
    {
        $this->message = $message;
    }

    public function handle(): void
    {
        $msg = $this->message;

        $tenantIdentifier = $msg['tenant_id'];
        $sessionId = (string) $msg['session_id'];
        $eventType = $msg['event_type'];
        $eventTimestamp = $msg['timestamp'];
        $eventHash = $msg['event_hash'];

        DB::beginTransaction();

        try {

            $tenant = Tenant::where('id', $tenantIdentifier)
                ->orWhere('external_id', (string) $tenantIdentifier)
                ->first();

            if (! $tenant) {
  
                $tenant = Tenant::create([
                    'name' => 'Tenant ' . $tenantIdentifier,
                    'external_id' => (string) $tenantIdentifier,
                ]);

                Log::info('Created tenant', [
                    'tenant_id' => $tenant->id, 
                    'external_id' => $tenantIdentifier
                ]);
            }

            $exists = Event::where('event_hash', $eventHash)
                ->where('tenant_id', $tenant->id)
                ->exists();

            if ($exists) {
                DB::commit();
                Log::info('Duplicate event ignored', [
                    'event_hash' => $eventHash,
                    'tenant_id' => $tenant->id,
                ]);
                return;
            }
            
            $session = TenantSession::firstOrCreate(
                ['tenant_id' => $tenant->id, 'session_id' => $sessionId],
                ['first_seen_at' => $eventTimestamp, 'last_seen_at' => $eventTimestamp]
            );

             // If session existed, update last_seen_at if eventTimestamp is newer
            if ($session->wasRecentlyCreated === false) {
                $shouldUpdate = false;
                if ($session->last_seen_at === null || $eventTimestamp->greaterThan($session->last_seen_at)) {
                    $session->last_seen_at = $eventTimestamp;
                    $shouldUpdate = true;
                }
                if ($session->first_seen_at === null || $eventTimestamp->lessThan($session->first_seen_at)) {
                    $session->first_seen_at = $eventTimestamp;
                    $shouldUpdate = true;
                }
                if ($shouldUpdate) {
                    $session->save();
                }
            }

            // Store Event
            try {
                $event = Event::create([
                    'tenant_id' => $tenant->id,
                    'tenant_session_id' => $session->id,
                    'event_type' => $eventType,
                    'event_hash' => $eventHash,
                    'event_timestamp' => $eventTimestamp,
                ]);

                Log::info('ProcessEventJob: Event stored', [
                    'tenant_id' => $tenant->id,
                    'event_hash' => $eventHash,
                ]);

            } catch (QueryException $e) {

                if ($this->isDuplicateKeyException($e)) {
                    DB::commit();
                    Log::warning('Duplicate event is not acceptable', [
                        'event_hash' => $eventHash,
                        'tenant_id' => $tenant->id,
                    ]);
                    return;
                }

                throw $e;

            }  catch (Exception $e) {
                DB::rollBack();

                Log::error('ProcessEventJob: unexpected error', [
                    'error' => $e->getMessage(),
                    'message' => $msg,
                ]);
                
                throw $e;
            }

            DB::commit();

            Log::info('Event processed successfully', [
                'event_id' => $event->id,
                'event_hash' => $eventHash,
                'tenant_id' => $tenant->id,
                'tenant_session_id' => $session->id,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('ProcessEventJob failed', [
                'error' => $e->getMessage(),
                'message' => $msg,
            ]);

            throw $e;
        }
    }

    protected function isDuplicateKeyException(QueryException $e): bool
    {
        $sqlState = (string) $e->getCode();
        $msg = strtolower($e->getMessage());

        if (in_array($sqlState, ['23000', '23505', '19'])) {
            return true;
        }

        if (str_contains($msg, 'unique') || str_contains($msg, 'duplicate')) {
            return true;
        }
        return false;
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ProcessEventJob failed', [
            'error' => $exception->getMessage(),
            'message' => $this->message,
        ]);
    }
}
