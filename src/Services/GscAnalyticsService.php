<?php

namespace Wonchoe\GscManager\Services;

use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Illuminate\Support\Carbon;
use Wonchoe\GscManager\Models\GscSearchAnalytic;
use Wonchoe\GscManager\Models\GscSite;
use Wonchoe\GscManager\Models\GscSyncLog;
use Wonchoe\GscManager\Support\GscDateRange;
use Wonchoe\GscManager\Support\GscRowHasher;

class GscAnalyticsService
{
    private const DIMENSIONS = ['date', 'hour', 'query', 'page', 'country', 'device', 'searchAppearance'];
    private const FILTER_DIMENSIONS = ['country', 'device', 'page', 'query', 'searchAppearance'];
    private const OPERATORS = ['contains', 'equals', 'notContains', 'notEquals', 'includingRegex', 'excludingRegex'];
    private const TYPES = ['web', 'image', 'video', 'news', 'discover', 'googleNews'];

    public function __construct(
        private readonly GscClientFactory $clients,
        private readonly GscRateLimiter $rateLimiter,
        private readonly GscErrorFormatter $errors,
    ) {
    }

    /**
     * @param array<int, string> $types
     * @param array<int, string> $dimensions
     * @param array<int, array<string, string>> $filters
     * @return array{rows_upserted: int, requests: int, errors: int}
     */
    public function syncSite(GscSite $site, GscDateRange $range, array $types = [], array $dimensions = [], array $filters = []): array
    {
        $started = Carbon::now();
        $stats = ['rows_upserted' => 0, 'requests' => 0, 'errors' => 0];

        $types = $this->cleanTypes($types ?: (array) config('gsc-manager.analytics.default_types', ['web']));
        $dimensions = $this->cleanDimensions($dimensions ?: (array) config('gsc-manager.analytics.default_dimensions', ['date', 'query', 'page', 'country', 'device']));
        $filters = $this->cleanFilters($filters);
        $rowLimit = min(25000, max(1, (int) config('gsc-manager.analytics.row_limit', 25000)));
        $maxPages = (bool) config('gsc-manager.analytics.paginate', true) ? max(1, (int) config('gsc-manager.analytics.max_pages_per_query', 20)) : 1;
        $dataState = (string) config('gsc-manager.analytics.data_state', 'final');
        $aggregationType = (string) config('gsc-manager.analytics.aggregation_type', 'auto');

        try {
            $service = $this->clients->make($site->credential->file_path, 'readonly');

            foreach ($types as $type) {
                $startRow = 0;
                $page = 0;

                do {
                    $body = [
                        'startDate' => $range->startDate(),
                        'endDate' => $range->endDate(),
                        'dimensions' => $dimensions,
                        'type' => $type,
                        'rowLimit' => $rowLimit,
                        'startRow' => $startRow,
                        'dataState' => $dataState,
                        'aggregationType' => $aggregationType,
                    ];

                    if ($filters !== []) {
                        $body['dimensionFilterGroups'] = [['filters' => $filters]];
                    }

                    $response = $this->rateLimiter->retry(fn () => $service->searchanalytics->query(
                        $site->site_url,
                        new SearchAnalyticsQueryRequest($body),
                    ));

                    $rows = $response->getRows() ?: [];
                    $stats['requests']++;

                    foreach ($rows as $row) {
                        $this->upsertRow($site, $type, $dimensions, $row, $aggregationType, $dataState);
                        $stats['rows_upserted']++;
                    }

                    $page++;
                    $startRow += $rowLimit;
                } while (count($rows) === $rowLimit && $page < $maxPages);
            }

            $site->forceFill(['last_analytics_synced_at' => Carbon::now(), 'last_error' => null])->save();
            $site->credential?->forceFill(['last_synced_at' => Carbon::now()])->save();
            $this->log($site, 'success', $started, 'Analytics sync completed.', $stats);
        } catch (\Throwable $exception) {
            $stats['errors']++;
            $error = $this->errors->format($exception);
            $site->forceFill(['status' => $site->status === 'approved' ? 'approved' : 'error', 'last_error' => $error])->save();
            $this->log($site, 'failed', $started, 'Analytics sync failed.', $stats, $error);
        }

        return $stats;
    }

    /**
     * @param array<int, string> $dimensions
     * @param mixed $row
     */
    private function upsertRow(GscSite $site, string $type, array $dimensions, mixed $row, string $aggregationType, string $dataState): void
    {
        $keys = method_exists($row, 'getKeys') ? ($row->getKeys() ?: []) : ($row['keys'] ?? []);
        $values = array_combine($dimensions, array_pad($keys, count($dimensions), null)) ?: [];

        $date = $values['date'] ?? null;
        $attributes = [
            'gsc_site_id' => $site->id,
            'date' => $date ?: null,
            'hour' => isset($values['hour']) ? (int) $values['hour'] : null,
            'query' => $values['query'] ?? null,
            'page' => $values['page'] ?? null,
            'country' => $values['country'] ?? null,
            'device' => $values['device'] ?? null,
            'search_appearance' => $values['searchAppearance'] ?? null,
            'type' => $type,
            'aggregation_type' => $aggregationType,
            'data_state' => $dataState,
            'clicks' => (int) (method_exists($row, 'getClicks') ? $row->getClicks() : ($row['clicks'] ?? 0)),
            'impressions' => (int) (method_exists($row, 'getImpressions') ? $row->getImpressions() : ($row['impressions'] ?? 0)),
            'ctr' => (float) (method_exists($row, 'getCtr') ? $row->getCtr() : ($row['ctr'] ?? 0)),
            'position' => (float) (method_exists($row, 'getPosition') ? $row->getPosition() : ($row['position'] ?? 0)),
            'raw' => method_exists($row, 'toSimpleObject') ? json_decode(json_encode($row->toSimpleObject()), true) : (array) $row,
        ];

        $hashParts = array_merge([
            'site_id' => $site->id,
            'type' => $type,
            'aggregation_type' => $aggregationType,
            'data_state' => $dataState,
        ], $values);
        $attributes['row_hash'] = GscRowHasher::make($hashParts);

        GscSearchAnalytic::updateOrCreate(
            [
                'gsc_site_id' => $site->id,
                'type' => $type,
                'date' => $attributes['date'],
                'row_hash' => $attributes['row_hash'],
            ],
            $attributes,
        );
    }

    /**
     * @param array<int, string> $types
     * @return array<int, string>
     */
    private function cleanTypes(array $types): array
    {
        return array_values(array_intersect($types, self::TYPES)) ?: ['web'];
    }

    /**
     * @param array<int, string> $dimensions
     * @return array<int, string>
     */
    private function cleanDimensions(array $dimensions): array
    {
        return array_values(array_intersect($dimensions, self::DIMENSIONS)) ?: ['date'];
    }

    /**
     * @param array<int, array<string, string>> $filters
     * @return array<int, array<string, string>>
     */
    private function cleanFilters(array $filters): array
    {
        return collect($filters)
            ->filter(fn (array $filter): bool => in_array($filter['dimension'] ?? '', self::FILTER_DIMENSIONS, true)
                && in_array($filter['operator'] ?? '', self::OPERATORS, true)
                && isset($filter['expression']))
            ->map(fn (array $filter): array => [
                'dimension' => $filter['dimension'],
                'operator' => $filter['operator'],
                'expression' => $filter['expression'],
            ])
            ->values()
            ->all();
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
            'type' => 'analytics',
            'status' => $status,
            'started_at' => $started,
            'finished_at' => Carbon::now(),
            'message' => $message,
            'stats' => $stats,
            'error' => $error,
        ]);
    }
}
