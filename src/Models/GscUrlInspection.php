<?php

namespace Wonchoe\GscManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GscUrlInspection extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_crawl_time' => 'datetime',
        'sitemap_urls' => 'array',
        'referring_urls' => 'array',
        'amp_result' => 'array',
        'mobile_usability_result' => 'array',
        'rich_results' => 'array',
        'raw' => 'array',
        'inspected_at' => 'datetime',
        'last_error' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(GscSite::class, 'gsc_site_id');
    }
}
