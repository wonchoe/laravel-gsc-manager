<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gsc_sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gsc_credential_id')->constrained('gsc_credentials')->cascadeOnDelete();
            $table->string('site_url')->unique();
            $table->string('property_type')->nullable();
            $table->string('permission_level')->nullable();
            $table->string('status')->default('discovered');
            $table->boolean('active')->default(false);
            $table->timestamp('last_discovered_at')->nullable();
            $table->timestamp('last_analytics_synced_at')->nullable();
            $table->timestamp('last_sitemaps_synced_at')->nullable();
            $table->timestamp('last_inspection_at')->nullable();
            $table->json('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_sites');
    }
};
