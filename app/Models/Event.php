<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    protected $fillable = [
        'tenant_id',
        'tenant_session_id',
        'event_type',
        'event_hash',
        'event_timestamp',
    ];

    protected $dates = [
        'event_timestamp',
        'created_at',
        'updated_at',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TenantSession::class, 'tenant_session_id');
    }
}
