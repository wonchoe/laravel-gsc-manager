<?php

namespace Wonchoe\GscManager\Console;

use Illuminate\Console\Command;
use Wonchoe\GscManager\Models\GscSite;
use Wonchoe\GscManager\Services\GscSearchAppearanceService;
use Wonchoe\GscManager\Support\GscDateRange;

class DiscoverSearchAppearancesCommand extends Command
{
    protected $signature = 'gsc:discover-search-appearances {--site=} {--type=*} {--days=30}';

    protected $description = 'Discover Search Appearance values by querying Search Analytics grouped by searchAppearance.';

    public function handle(GscSearchAppearanceService $service): int
    {
        $range = GscDateRange::fromOptions(['days' => $this->option('days')]);
        $query = GscSite::query()->with('credential')->where('status', 'approved')->where('active', true);

        if ($this->option('site')) {
            $query->where('site_url', $this->option('site'));
        }

        $summary = ['sites' => 0, 'appearances_upserted' => 0, 'requests' => 0, 'errors' => 0];

        $query->chunkById(50, function ($sites) use ($service, $range, &$summary): void {
            foreach ($sites as $site) {
                $stats = $service->discover($site, $range, (array) $this->option('type'));
                $summary['sites']++;
                $summary['appearances_upserted'] += $stats['appearances_upserted'];
                $summary['requests'] += $stats['requests'];
                $summary['errors'] += $stats['errors'];
            }
        });

        $this->table(['Metric', 'Value'], collect($summary)->map(fn ($value, $key): array => [$key, $value])->values()->all());

        return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
