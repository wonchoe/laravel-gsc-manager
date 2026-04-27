<?php

namespace Wonchoe\GscManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GscSitemap extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_pending' => 'boolean',
        'is_sitemaps_index' => 'boolean',
        'last_submitted_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
        'warnings' => 'integer',
        'errors' => 'integer',
        'raw' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(GscSite::class, 'gsc_site_id');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(GscSitemapContent::class);
    }
}
