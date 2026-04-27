<?php

namespace Wonchoe\GscManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GscSyncLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'stats' => 'array',
        'error' => 'array',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(GscCredential::class, 'gsc_credential_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(GscSite::class, 'gsc_site_id');
    }
}
