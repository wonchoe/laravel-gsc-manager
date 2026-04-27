<?php

namespace Wonchoe\GscManager\Services;

use Google\Service\SearchConsole\InspectUrlIndexRequest;
use Illuminate\Support\Carbon;
use Wonchoe\GscManager\Models\GscSite;
use Wonchoe\GscManager\Models\GscSyncLog;
use Wonchoe\GscManager\Models\GscUrlInspection;
use Wonchoe\GscManager\Support\GscRowHasher;

class GscUrlInspectionService
{
    public function __construct(
        private readonly GscClientFactory $clients,
        private readonly GscRateLimiter $rateLimiter,
        private readonly GscErrorFormatter $errors,
    ) {
    }

    public function inspect(GscSite $site, string $inspectionUrl, ?string $languageCode = null): GscUrlInspection
    {
        if (! (bool) config('gsc-manager.url_inspection.enabled', true)) {
            throw new \RuntimeException('URL Inspection is disabled by config.');
        }

        $this->assertWithinLimits($site);
        $started = Carbon::now();

        try {
            $service = $this->clients->make($site->credential->file_path, 'readonly');
            $request = new InspectUrlIndexRequest([
                'inspectionUrl' => $inspectionUrl,
                'siteUrl' => $site->site_url,
                'languageCode' => $languageCode ?: config('gsc-manager.url_inspection.default_language_code', 'en-US'),
            ]);

            $response = $this->rateLimiter->retry(fn () => $service->urlInspection_index->inspect($request));
            $raw = json_decode(json_encode($response->toSimpleObject()), true, 512, JSON_THROW_ON_ERROR);
            $inspection = $this->saveInspection($site, $inspectionUrl, $raw);
            $site->forceFill(['last_inspection_at' => Carbon::now(), 'last_error' => null])->save();
            $site->credential?->forceFill(['last_synced_at' => Carbon::now()])->save();

            $this->log($site, 'success', $started, 'URL inspected.', ['url' => $inspectionUrl]);

            return $inspection;
        } catch (\Throwable $exception) {
            $error = $this->errors->format($exception);
            $inspection = GscUrlInspection::updateOrCreate(
                ['gsc_site_id' => $site->id, 'inspection_url_hash' => GscRowHasher::make(['inspection_url' => $inspectionUrl])],
                [
                    'inspection_url' => $inspectionUrl,
                    'last_error' => $error,
                    'inspected_at' => Carbon::now(),
                ],
            );
            $site->forceFill(['last_error' => $error])->save();
            $this->log($site, 'failed', $started, 'URL inspection failed.', ['url' => $inspectionUrl], $error);

            return $inspection;
        }
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function saveInspection(GscSite $site, string $inspectionUrl, array $raw): GscUrlInspection
    {
        $result = $raw['inspectionResult'] ?? [];
        $index = $result['indexStatusResult'] ?? [];

        return GscUrlInspection::updateOrCreate(
            ['gsc_site_id' => $site->id, 'inspection_url_hash' => GscRowHasher::make(['inspection_url' => $inspectionUrl])],
            [
                'inspection_url' => $inspectionUrl,
                'verdict' => $index['verdict'] ?? null,
                'coverage_state' => $index['coverageState'] ?? null,
                'robots_txt_state' => $index['robotsTxtState'] ?? null,
                'indexing_state' => $index['indexingState'] ?? null,
                'page_fetch_state' => $index['pageFetchState'] ?? null,
                'google_canonical' => $index['googleCanonical'] ?? null,
                'user_canonical' => $index['userCanonical'] ?? null,
                'last_crawl_time' => isset($index['lastCrawlTime']) ? Carbon::parse($index['lastCrawlTime']) : null,
                'crawled_as' => $index['crawledAs'] ?? null,
                'sitemap_urls' => $index['sitemap'] ?? null,
                'referring_urls' => $index['referringUrls'] ?? null,
                'inspection_result_link' => $result['inspectionResultLink'] ?? null,
                'amp_result' => $result['ampResult'] ?? null,
                'mobile_usability_result' => $result['mobileUsabilityResult'] ?? null,
                'rich_results' => $result['richResultsResult'] ?? null,
                'raw' => $raw,
                'inspected_at' => Carbon::now(),
                'last_error' => null,
            ],
        );
    }

    private function assertWithinLimits(GscSite $site): void
    {
        $dailyLimit = (int) config('gsc-manager.url_inspection.daily_limit_per_site', 2000);
        $qpmLimit = (int) config('gsc-manager.url_inspection.qpm_limit_per_site', 600);

        $today = GscUrlInspection::query()
            ->where('gsc_site_id', $site->id)
            ->whereDate('inspected_at', Carbon::today())
            ->count();

        if ($today >= $dailyLimit) {
            throw new \RuntimeException('URL Inspection daily limit reached for this site.');
        }

        $lastMinute = GscUrlInspection::query()
            ->where('gsc_site_id', $site->id)
            ->where('inspected_at', '>=', Carbon::now()->subMinute())
            ->count();

        if ($lastMinute >= $qpmLimit) {
            throw new \RuntimeException('URL Inspection per-minute limit reached for this site.');
        }
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
            'type' => 'inspection',
            'status' => $status,
            'started_at' => $started,
            'finished_at' => Carbon::now(),
            'message' => $message,
            'stats' => $stats,
            'error' => $error,
        ]);
    }
}
