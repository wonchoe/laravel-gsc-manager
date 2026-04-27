<?php

namespace Wonchoe\GscManager\Services;

use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Illuminate\Support\Carbon;
use Wonchoe\GscManager\Models\GscSearchAppearance;
use Wonchoe\GscManager\Models\GscSite;
use Wonchoe\GscManager\Models\GscSyncLog;
use Wonchoe\GscManager\Support\GscDateRange;

class GscSearchAppearanceService
{
    private const TYPES = ['web', 'image', 'video', 'news', 'discover', 'googleNews'];

    public function __construct(
        private readonly GscClientFactory $clients,
        private readonly GscRateLimiter $rateLimiter,
        private readonly GscErrorFormatter $errors,
    ) {
    }

    /**
     * @param array<int, string> $types
     * @return array{appearances_upserted: int, requests: int, errors: int}
     */
    public function discover(GscSite $site, GscDateRange $range, array $types = []): array
    {
        $started = Carbon::now();
        $stats = ['appearances_upserted' => 0, 'requests' => 0, 'errors' => 0];
        $types = array_values(array_intersect($types ?: (array) config('gsc-manager.analytics.default_types', ['web']), self::TYPES)) ?: ['web'];

        try {
            $service = $this->clients->make($site->credential->file_path, 'readonly');

            foreach ($types as $type) {
                $request = new SearchAnalyticsQueryRequest([
                    'startDate' => $range->startDate(),
                    'endDate' => $range->endDate(),
                    'dimensions' => ['searchAppearance'],
                    'type' => $type,
                    'rowLimit' => min(25000, (int) config('gsc-manager.analytics.row_limit', 25000)),
                    'dataState' => config('gsc-manager.analytics.data_state', 'final'),
                    'aggregationType' => config('gsc-manager.analytics.aggregation_type', 'auto'),
                ]);

                $response = $this->rateLimiter->retry(fn () => $service->searchanalytics->query($site->site_url, $request));
                $stats['requests']++;

                foreach ($response->getRows() ?: [] as $row) {
                    $keys = $row->getKeys() ?: [];
                    $value = (string) ($keys[0] ?? '');

                    if ($value === '') {
                        continue;
                    }

                    $existing = GscSearchAppearance::query()
                        ->where('gsc_site_id', $site->id)
                        ->where('type', $type)
                        ->where('search_appearance', $value)
                        ->first();

                    GscSearchAppearance::updateOrCreate(
                        ['gsc_site_id' => $site->id, 'type' => $type, 'search_appearance' => $value],
                        [
                            'clicks' => (int) $row->getClicks(),
                            'impressions' => (int) $row->getImpressions(),
                            'ctr' => (float) $row->getCtr(),
                            'position' => (float) $row->getPosition(),
                            'first_seen_at' => $existing?->first_seen_at ?? Carbon::now(),
                            'last_seen_at' => Carbon::now(),
                            'raw' => json_decode(json_encode($row->toSimpleObject()), true),
                        ],
                    );
                    $stats['appearances_upserted']++;
                }
            }

            $site->credential?->forceFill(['last_synced_at' => Carbon::now()])->save();
            $this->log($site, 'success', $started, 'Search appearances discovered.', $stats);
        } catch (\Throwable $exception) {
            $stats['errors']++;
            $error = $this->errors->format($exception);
            $site->forceFill(['last_error' => $error])->save();
            $this->log($site, 'failed', $started, 'Search appearance discovery failed.', $stats, $error);
        }

        return $stats;
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
            'type' => 'search_appearance',
            'status' => $status,
            'started_at' => $started,
            'finished_at' => Carbon::now(),
            'message' => $message,
            'stats' => $stats,
            'error' => $error,
        ]);
    }
}
