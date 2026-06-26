<?php

namespace Wonchoe\GscManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GscCredential extends Model
{
    protected $guarded = [];

    protected $hidden = ['file_path', 'token_data', 'last_error'];

    protected $casts = [
        'active' => 'boolean',
        'scopes' => 'array',
        'last_error' => 'array',
        'last_discovered_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'token_data' => 'array',
    ];

    public function sites(): HasMany
    {
        return $this->hasMany(GscSite::class);
    }
}
