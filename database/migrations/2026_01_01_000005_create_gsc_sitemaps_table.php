<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gsc_sitemaps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gsc_site_id')->constrained('gsc_sites')->cascadeOnDelete();
            $table->text('path');
            $table->string('path_hash', 64)->index();
            $table->string('type')->nullable();
            $table->boolean('is_pending')->default(false);
            $table->boolean('is_sitemaps_index')->default(false);
            $table->timestamp('last_submitted_at')->nullable();
            $table->timestamp('last_downloaded_at')->nullable();
            $table->unsignedInteger('warnings')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['gsc_site_id', 'path_hash'], 'gsc_sitemaps_site_path_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_sitemaps');
    }
};
