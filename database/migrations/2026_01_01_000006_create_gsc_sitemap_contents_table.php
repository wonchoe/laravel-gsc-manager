<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gsc_sitemap_contents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gsc_sitemap_id')->constrained('gsc_sitemaps')->cascadeOnDelete();
            $table->string('content_type')->index();
            $table->unsignedInteger('submitted')->nullable();
            $table->unsignedInteger('indexed')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['gsc_sitemap_id', 'content_type'], 'gsc_sitemap_contents_sitemap_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_sitemap_contents');
    }
};
