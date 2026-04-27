<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gsc_search_analytics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gsc_site_id')->constrained('gsc_sites')->cascadeOnDelete();
            $table->date('date')->nullable()->index();
            $table->unsignedTinyInteger('hour')->nullable()->index();
            $table->text('query')->nullable();
            $table->text('page')->nullable();
            $table->string('country')->nullable()->index();
            $table->string('device')->nullable()->index();
            $table->string('search_appearance')->nullable()->index();
            $table->string('type')->default('web')->index();
            $table->string('aggregation_type')->nullable();
            $table->string('data_state')->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 12, 8)->default(0);
            $table->decimal('position', 12, 4)->default(0);
            $table->string('row_hash', 64);
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['gsc_site_id', 'type', 'date', 'row_hash'], 'gsc_analytics_site_type_date_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_search_analytics');
    }
};
