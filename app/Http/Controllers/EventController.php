<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Jobs\ProcessEventJob;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class EventController extends Controller
{
    public function store(StoreEventRequest $request): JsonResponse
    {
        $encoded = $request->input('payload');

        $decodedJson = base64_decode($encoded, true);
        if ($decodedJson === false) {
            return response()->json(['error' => 'payload decoding failed'], 400);
        }

        $payload = json_decode($decodedJson, true);
        if (! is_array($payload)) {
            return response()->json(['error' => 'payload is not valid JSON after decoding'], 400);
        }

        $required = ['tenant_id', 'session_id', 'event_type', 'timestamp'];
        foreach ($required as $field) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                return response()->json(['error' => "missing or empty field: {$field}"], 400);
            }
        }

        try {
            $eventTimestamp = Carbon::parse($payload['timestamp'])->toIso8601String();
        } catch (\Exception $e) {
            return response()->json(['error' => 'invalid timestamp format'], 400);
        }

        $hashInput = implode('|', [
            (string) $payload['tenant_id'],
            (string) $payload['session_id'],
            (string) $payload['event_type'],
            $eventTimestamp,
        ]);

        $eventHash = hash('sha256', $hashInput);

        $message = [
            'tenant_id' => $payload['tenant_id'],
            'session_id' => $payload['session_id'],
            'event_type' => $payload['event_type'],
            'timestamp' => $eventTimestamp,
            'event_hash' => $eventHash,
            'raw_payload' => $payload,
        ];

        ProcessEventJob::dispatch($message);

        return response()->json(['status' => 'accepted'], 202);
    }
}
    