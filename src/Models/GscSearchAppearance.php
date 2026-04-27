<?php

namespace Wonchoe\GscManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GscSearchAppearance extends Model
{
    protected $guarded = [];

    protected $casts = [
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'decimal:8',
        'position' => 'decimal:4',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'raw' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(GscSite::class, 'gsc_site_id');
    }
}
