<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GscClientFactory reads $credential->auth_type and $credential->token_data, but the original
 * gsc_credentials migration never created them. Without these columns the OAuth path throws
 * "Unknown column". Service-account credentials don't need them (guarded by ?? null), but the
 * columns must exist for the OAuth flow to function and to stop shipping broken code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gsc_credentials', function (Blueprint $table): void {
            if (! Schema::hasColumn('gsc_credentials', 'auth_type')) {
                $table->string('auth_type')->default('service_account')->after('id');
            }
            if (! Schema::hasColumn('gsc_credentials', 'token_data')) {
                // Holds OAuth access/refresh tokens (JSON). Hidden on the model; keep out of logs.
                $table->text('token_data')->nullable()->after('auth_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gsc_credentials', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['auth_type', 'token_data'],
                fn (string $c): bool => Schema::hasColumn('gsc_credentials', $c),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
