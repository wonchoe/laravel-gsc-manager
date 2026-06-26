<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard/report queries filter by (gsc_site_id, type, date) but cannot use the unique
 * [gsc_site_id, type, date, row_hash] index without row_hash, so they fall back to scans.
 * Add a covering composite index for the common reporting access path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gsc_search_analytics', function (Blueprint $table): void {
            $table->index(['gsc_site_id', 'type', 'date'], 'gsc_sa_site_type_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('gsc_search_analytics', function (Blueprint $table): void {
            $table->dropIndex('gsc_sa_site_type_date_idx');
        });
    }
};
