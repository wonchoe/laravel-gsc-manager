<?php

namespace Wonchoe\GscManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GscSitemapContent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'submitted' => 'integer',
        'indexed' => 'integer',
        'raw' => 'array',
    ];

    public function sitemap(): BelongsTo
    {
        return $this->belongsTo(GscSitemap::class, 'gsc_sitemap_id');
    }
}
