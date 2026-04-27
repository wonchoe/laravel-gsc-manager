<?php

namespace Wonchoe\GscManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GscSearchAnalytic extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'decimal:8',
        'position' => 'decimal:4',
        'raw' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(GscSite::class, 'gsc_site_id');
    }
}
