<?php

namespace Wonchoe\GscManager\Services;

use Illuminate\Support\Carbon;
use Wonchoe\GscManager\Models\GscSite;
use Wonchoe\GscManager\Models\GscSitemap;
use Wonchoe\GscManager\Models\GscSitemapContent;
use Wonchoe\GscManager\Models\GscSyncLog;

class GscSitemapService
{
    public function __construct(
        private readonly GscClientFactory $clients,
        private readonly GscRateLimiter $rateLimiter,
        private readonly GscErrorFormatter $errors,
    ) {
    }

    /**
     * @return array{sitemaps_upserted: int, contents_upserted: int, errors: int}
     */
    public function listSitemaps(GscSite $site): array
    {
        $started = Carbon::now();
        $stats = ['sitemaps_upserted' => 0, 'contents_upserted' => 0, 'errors' => 0];

        if (! (bool) config('gsc-manager.sitemaps.sync_enabled', true)) {
            $this->log($site, 'success', $started, 'Sitemap sync is disabled.', $stats);
            return $stats;
        }

        try {
            $service = $this->clients->make($site->credential->file_path, 'readonly');
            $response = $this->rateLimiter->retry(fn () => $service->sitemaps->listSitemaps($site->site_url));

            foreach ($response->getSitemap() ?: [] as $sitemap) {
                $saved = $this->saveSitemap($site, $sitemap);
                $stats['sitemaps_upserted']++;
                $stats['contents_upserted'] += $saved->contents()->count();
            }

            $site->forceFill(['last_sitemaps_synced_at' => Carbon::now(), 'last_error' => null])->save();
            $site->credential?->forceFill(['last_synced_at' => Carbon::now()])->save();
            $this->log($site, 'success', $started, 'Sitemap sync completed.', $stats);
        } catch (\Throwable $exception) {
            $stats['errors']++;
            $error = $this->errors->format($exception);
            $site->forceFill(['last_error' => $error])->save();
            $this->log($site, 'failed', $started, 'Sitemap sync failed.', $stats, $error);
        }

        return $stats;
    }

    public function getSitemap(GscSite $site, string $sitemapUrl): GscSitemap
    {
        $service = $this->clients->make($site->credential->file_path, 'readonly');
        $response = $this->rateLimiter->retry(fn () => $service->sitemaps->get($site->site_url, $sitemapUrl));

        return $this->saveSitemap($site, $response);
    }

    public function submitSitemap(GscSite $site, string $sitemapUrl): void
    {
        if (! (bool) config('gsc-manager.sitemaps.submit_enabled', false)) {
            throw new \RuntimeException('Sitemap submission is disabled by config.');
        }

        $service = $this->clients->make($site->credential->file_path, 'full');
        $this->rateLimiter->retry(fn () => $service->sitemaps->submit($site->site_url, $sitemapUrl));
    }

    public function deleteSitemap(GscSite $site, string $sitemapUrl): void
    {
        if (! (bool) config('gsc-manager.sitemaps.delete_enabled', false)) {
            throw new \RuntimeException('Sitemap deletion is disabled by config.');
        }

        $service = $this->clients->make($site->credential->file_path, 'full');
        $this->rateLimiter->retry(fn () => $service->sitemaps->delete($site->site_url, $sitemapUrl));
    }

    private function saveSitemap(GscSite $site, mixed $sitemap): GscSitemap
    {
        $path = (string) $sitemap->getPath();
        $model = GscSitemap::updateOrCreate(
            ['gsc_site_id' => $site->id, 'path_hash' => hash('sha256', $path)],
            [
                'path' => $path,
                'type' => $sitemap->getType(),
                'is_pending' => (bool) $sitemap->getIsPending(),
                'is_sitemaps_index' => (bool) $sitemap->getIsSitemapsIndex(),
                'last_submitted_at' => $this->dateOrNull($sitemap->getLastSubmitted()),
                'last_downloaded_at' => $this->dateOrNull($sitemap->getLastDownloaded()),
                'warnings' => (int) $sitemap->getWarnings(),
                'errors' => (int) $sitemap->getErrors(),
                'raw' => json_decode(json_encode($sitemap->toSimpleObject()), true),
            ],
        );

        foreach ($sitemap->getContents() ?: [] as $content) {
            GscSitemapContent::updateOrCreate(
                ['gsc_sitemap_id' => $model->id, 'content_type' => (string) $content->getType()],
                [
                    'submitted' => $content->getSubmitted(),
                    'indexed' => method_exists($content, 'getIndexed') ? $content->getIndexed() : null,
                    'raw' => json_decode(json_encode($content->toSimpleObject()), true),
                ],
            );
        }

        return $model;
    }

    private function dateOrNull(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    /**
     * @param array<string, mixed> $stats
     * @param array<string, mixed>|null $error
     */
    private function log(GscSite $site, string $status, Carbon $started, string $message, array $stats, ?array $error = null): void
    {
        GscSyncLog::create([
            'gsc_credential_id' => $site->gsc_credential_id,
            'gsc_site_id' => $site->id,
            'type' => 'sitemap',
            'status' => $status,
            'started_at' => $started,
            'finished_at' => Carbon::now(),
            'message' => $message,
            'stats' => $stats,
            'error' => $error,
        ]);
    }
}
