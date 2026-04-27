<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gsc_search_appearances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gsc_site_id')->constrained('gsc_sites')->cascadeOnDelete();
            $table->string('type')->default('web')->index();
            $table->string('search_appearance')->index();
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 12, 8)->default(0);
            $table->decimal('position', 12, 4)->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['gsc_site_id', 'type', 'search_appearance'], 'gsc_search_appearances_site_type_value_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_search_appearances');
    }
};
