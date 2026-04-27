<?php

use Illuminate\Support\Facades\Route;
use Wonchoe\GscManager\Http\Controllers\GscCredentialController;
use Wonchoe\GscManager\Http\Controllers\GscDashboardController;
use Wonchoe\GscManager\Http\Controllers\GscDiscoveryController;
use Wonchoe\GscManager\Http\Controllers\GscSearchAppearanceController;
use Wonchoe\GscManager\Http\Controllers\GscSiteController;
use Wonchoe\GscManager\Http\Controllers\GscSitemapController;
use Wonchoe\GscManager\Http\Controllers\GscSyncController;
use Wonchoe\GscManager\Http\Controllers\GscUrlInspectionController;

Route::prefix(config('gsc-manager.routes.prefix', 'api/gsc'))
    ->middleware(config('gsc-manager.routes.middleware', ['api']))
    ->name(config('gsc-manager.routes.name_prefix', 'gsc-manager.'))
    ->group(function (): void {
        Route::get('/credentials', [GscCredentialController::class, 'index'])->name('credentials.index');
        Route::get('/credentials/{credential}', [GscCredentialController::class, 'show'])->name('credentials.show');
        Route::post('/discover', [GscDiscoveryController::class, 'discover'])->name('discover');
        Route::post('/access-check', [GscDiscoveryController::class, 'accessCheck'])->name('access-check');

        Route::get('/sites', [GscSiteController::class, 'index'])->name('sites.index');
        Route::get('/sites/{site}', [GscSiteController::class, 'show'])->name('sites.show');
        Route::post('/sites/{site}/approve', [GscSiteController::class, 'approve'])->name('sites.approve');
        Route::post('/sites/{site}/disable', [GscSiteController::class, 'disable'])->name('sites.disable');
        Route::post('/sites/{site}/sync-analytics', [GscSyncController::class, 'syncAnalytics'])->name('sites.sync-analytics');
        Route::post('/sites/{site}/sync-sitemaps', [GscSyncController::class, 'syncSitemaps'])->name('sites.sync-sitemaps');
        Route::post('/sites/{site}/discover-search-appearances', [GscSyncController::class, 'discoverSearchAppearances'])->name('sites.discover-search-appearances');

        Route::get('/sites/{site}/analytics', [GscSyncController::class, 'analytics'])->name('sites.analytics.index');
        Route::get('/sites/{site}/analytics/summary', [GscSyncController::class, 'analyticsSummary'])->name('sites.analytics.summary');
        Route::get('/sites/{site}/search-appearances', [GscSearchAppearanceController::class, 'index'])->name('sites.search-appearances.index');
        Route::get('/sites/{site}/sitemaps', [GscSitemapController::class, 'index'])->name('sites.sitemaps.index');
        Route::post('/sites/{site}/sitemaps/submit', [GscSitemapController::class, 'submit'])->name('sites.sitemaps.submit');
        Route::delete('/sites/{site}/sitemaps', [GscSitemapController::class, 'delete'])->name('sites.sitemaps.delete');

        Route::post('/sites/{site}/inspect-url', [GscUrlInspectionController::class, 'inspect'])->name('sites.inspect-url');
        Route::get('/sites/{site}/inspections', [GscUrlInspectionController::class, 'index'])->name('sites.inspections.index');

        Route::get('/dashboard', [GscDashboardController::class, 'dashboard'])->name('dashboard');
        Route::get('/sync-logs', [GscSyncController::class, 'syncLogs'])->name('sync-logs.index');
    });
