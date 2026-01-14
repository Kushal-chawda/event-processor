<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class TenantSession extends Model
{
    protected $table = 'tenant_sessions';

    protected $fillable = [
        'tenant_id',
        'session_id',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $dates = [
        'first_seen_at',
        'last_seen_at',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'tenant_session_id');
    }
}
