<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gsc_url_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gsc_site_id')->constrained('gsc_sites')->cascadeOnDelete();
            $table->text('inspection_url');
            $table->string('inspection_url_hash', 64)->index();
            $table->string('verdict')->nullable();
            $table->string('coverage_state')->nullable();
            $table->string('robots_txt_state')->nullable();
            $table->string('indexing_state')->nullable();
            $table->string('page_fetch_state')->nullable();
            $table->text('google_canonical')->nullable();
            $table->text('user_canonical')->nullable();
            $table->timestamp('last_crawl_time')->nullable();
            $table->string('crawled_as')->nullable();
            $table->json('sitemap_urls')->nullable();
            $table->json('referring_urls')->nullable();
            $table->text('inspection_result_link')->nullable();
            $table->json('amp_result')->nullable();
            $table->json('mobile_usability_result')->nullable();
            $table->json('rich_results')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->json('last_error')->nullable();
            $table->timestamps();

            $table->unique(['gsc_site_id', 'inspection_url_hash'], 'gsc_url_inspections_site_url_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_url_inspections');
    }
};
