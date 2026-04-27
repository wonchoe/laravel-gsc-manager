<?php

namespace Wonchoe\GscManager\Console;

use Illuminate\Console\Command;
use Wonchoe\GscManager\Models\GscSite;
use Wonchoe\GscManager\Services\GscAnalyticsService;
use Wonchoe\GscManager\Support\GscDateRange;

class SyncGscAnalyticsCommand extends Command
{
    protected $signature = 'gsc:sync-analytics
        {--credential= : Specific JSON filename}
        {--site= : Specific site_url}
        {--from= : Start date Y-m-d}
        {--to= : End date Y-m-d}
        {--days= : Number of days back}
        {--type=* : Search type: web,image,video,news,discover,googleNews}
        {--dimensions=* : Dimensions}
        {--data-state= : final|all|hourly_all}
        {--aggregation= : auto|byPage|byProperty}';

    protected $description = 'Sync Search Analytics rows for approved active sites.';

    public function handle(GscAnalyticsService $analytics): int
    {
        $this->applyRuntimeConfig();
        $range = GscDateRange::fromOptions([
            'from' => $this->option('from'),
            'to' => $this->option('to'),
            'days' => $this->option('days'),
        ]);

        $query = GscSite::query()->with('credential')->where('status', 'approved')->where('active', true);

        if ($this->option('credential')) {
            $query->whereHas('credential', fn ($credential) => $credential->where('file_name', $this->option('credential')));
        }

        if ($this->option('site')) {
            $query->where('site_url', $this->option('site'));
        }

        $summary = ['sites' => 0, 'rows_upserted' => 0, 'requests' => 0, 'errors' => 0];

        $query->chunkById(50, function ($sites) use ($analytics, $range, &$summary): void {
            foreach ($sites as $site) {
                $stats = $analytics->syncSite($site, $range, (array) $this->option('type'), (array) $this->option('dimensions'));
                $summary['sites']++;
                $summary['rows_upserted'] += $stats['rows_upserted'];
                $summary['requests'] += $stats['requests'];
                $summary['errors'] += $stats['errors'];
            }
        });

        $this->table(['Metric', 'Value'], collect($summary)->map(fn ($value, $key): array => [$key, $value])->values()->all());

        return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function applyRuntimeConfig(): void
    {
        if ($this->option('data-state')) {
            config(['gsc-manager.analytics.data_state' => $this->option('data-state')]);
        }

        if ($this->option('aggregation')) {
            config(['gsc-manager.analytics.aggregation_type' => $this->option('aggregation')]);
        }
    }
}
