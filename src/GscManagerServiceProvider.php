<?php

namespace Wonchoe\GscManager;

use Illuminate\Support\ServiceProvider;
use Wonchoe\GscManager\Console\CheckGscAccessCommand;
use Wonchoe\GscManager\Console\DiscoverGscSitesCommand;
use Wonchoe\GscManager\Console\DiscoverSearchAppearancesCommand;
use Wonchoe\GscManager\Console\InspectGscUrlsCommand;
use Wonchoe\GscManager\Console\PruneGscDataCommand;
use Wonchoe\GscManager\Console\SyncGscAnalyticsCommand;
use Wonchoe\GscManager\Console\SyncGscSitemapsCommand;
use Wonchoe\GscManager\Services\GscAccessCheckService;
use Wonchoe\GscManager\Services\GscAnalyticsService;
use Wonchoe\GscManager\Services\GscClientFactory;
use Wonchoe\GscManager\Services\GscCredentialScanner;
use Wonchoe\GscManager\Services\GscDiscoveryService;
use Wonchoe\GscManager\Services\GscErrorFormatter;
use Wonchoe\GscManager\Services\GscIndexingApiService;
use Wonchoe\GscManager\Services\GscRateLimiter;
use Wonchoe\GscManager\Services\GscSearchAppearanceService;
use Wonchoe\GscManager\Services\GscSitemapService;
use Wonchoe\GscManager\Services\GscUrlInspectionService;

class GscManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/gsc-manager.php', 'gsc-manager');

        foreach ([
            GscErrorFormatter::class,
            GscIndexingApiService::class,
            GscRateLimiter::class,
            GscCredentialScanner::class,
            GscClientFactory::class,
            GscDiscoveryService::class,
            GscAnalyticsService::class,
            GscSitemapService::class,
            GscUrlInspectionService::class,
            GscAccessCheckService::class,
            GscSearchAppearanceService::class,
        ] as $service) {
            $this->app->singleton($service);
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Secure-by-default: routes are off unless explicitly enabled (they include credential
        // listing + sitemap delete and must never be served unauthenticated).
        if ((bool) config('gsc-manager.routes.enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }

        // Register commands unconditionally so Artisan::call() works from web context too
        $this->commands([
            DiscoverGscSitesCommand::class,
            CheckGscAccessCommand::class,
            SyncGscAnalyticsCommand::class,
            DiscoverSearchAppearancesCommand::class,
            SyncGscSitemapsCommand::class,
            InspectGscUrlsCommand::class,
            PruneGscDataCommand::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->validateConfig();
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/gsc-manager.php' => config_path('gsc-manager.php'),
            ], 'gsc-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'gsc-migrations');
        }
    }

    /**
     * Fail fast (console only) on clearly-invalid config instead of deep at runtime.
     */
    private function validateConfig(): void
    {
        $scopes = (array) config('gsc-manager.scopes', []);
        $defaultScope = (string) config('gsc-manager.default_scope', 'readonly');
        if (! array_key_exists($defaultScope, $scopes)) {
            throw new \RuntimeException("gsc-manager.default_scope '{$defaultScope}' is not defined in gsc-manager.scopes.");
        }

        foreach (['analytics.row_limit', 'analytics.max_pages_per_query', 'rate_limits.max_retries'] as $key) {
            $value = config("gsc-manager.{$key}");
            if ($value !== null && (! is_numeric($value) || (int) $value < 1)) {
                throw new \RuntimeException("gsc-manager.{$key} must be a positive integer.");
            }
        }
    }
}
