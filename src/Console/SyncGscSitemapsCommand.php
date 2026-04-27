<?php

namespace Wonchoe\GscManager\Console;

use Illuminate\Console\Command;
use Wonchoe\GscManager\Models\GscSite;
use Wonchoe\GscManager\Services\GscSitemapService;

class SyncGscSitemapsCommand extends Command
{
    protected $signature = 'gsc:sync-sitemaps {--credential=} {--site=}';

    protected $description = 'Sync sitemaps for approved active GSC sites.';

    public function handle(GscSitemapService $sitemaps): int
    {
        $query = GscSite::query()->with('credential')->where('status', 'approved')->where('active', true);

        if ($this->option('credential')) {
            $query->whereHas('credential', fn ($credential) => $credential->where('file_name', $this->option('credential')));
        }

        if ($this->option('site')) {
            $query->where('site_url', $this->option('site'));
        }

        $summary = ['sites' => 0, 'sitemaps_upserted' => 0, 'contents_upserted' => 0, 'errors' => 0];

        $query->chunkById(50, function ($sites) use ($sitemaps, &$summary): void {
            foreach ($sites as $site) {
                $stats = $sitemaps->listSitemaps($site);
                $summary['sites']++;
                $summary['sitemaps_upserted'] += $stats['sitemaps_upserted'];
                $summary['contents_upserted'] += $stats['contents_upserted'];
                $summary['errors'] += $stats['errors'];
            }
        });

        $this->table(['Metric', 'Value'], collect($summary)->map(fn ($value, $key): array => [$key, $value])->values()->all());

        return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
