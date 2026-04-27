<?php

namespace Wonchoe\GscManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GscSite extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'last_error' => 'array',
        'last_discovered_at' => 'datetime',
        'last_analytics_synced_at' => 'datetime',
        'last_sitemaps_synced_at' => 'datetime',
        'last_inspection_at' => 'datetime',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(GscCredential::class, 'gsc_credential_id');
    }

    public function searchAnalytics(): HasMany
    {
        return $this->hasMany(GscSearchAnalytic::class);
    }

    public function sitemaps(): HasMany
    {
        return $this->hasMany(GscSitemap::class);
    }

    public function urlInspections(): HasMany
    {
        return $this->hasMany(GscUrlInspection::class);
    }

    public function searchAppearances(): HasMany
    {
        return $this->hasMany(GscSearchAppearance::class);
    }
}
