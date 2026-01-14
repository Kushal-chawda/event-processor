<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = ['name'];

    public function sessions(): HasMany
    {
        return $this->hasMany(TenantSession::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

}
