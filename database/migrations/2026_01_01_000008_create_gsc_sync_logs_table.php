<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gsc_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gsc_credential_id')->nullable()->constrained('gsc_credentials')->nullOnDelete();
            $table->foreignId('gsc_site_id')->nullable()->constrained('gsc_sites')->nullOnDelete();
            $table->string('type');
            $table->string('status');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('message')->nullable();
            $table->json('stats')->nullable();
            $table->json('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_sync_logs');
    }
};
