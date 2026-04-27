<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gsc_credentials', function (Blueprint $table): void {
            $table->id();
            $table->string('file_name')->unique();
            $table->string('file_path');
            $table->string('client_email')->nullable()->index();
            $table->string('project_id')->nullable()->index();
            $table->string('label')->nullable();
            $table->json('scopes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_discovered_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_credentials');
    }
};
