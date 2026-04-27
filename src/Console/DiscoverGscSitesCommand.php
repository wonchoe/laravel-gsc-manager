<?php

namespace Wonchoe\GscManager\Console;

use Illuminate\Console\Command;
use Wonchoe\GscManager\Services\GscCredentialScanner;
use Wonchoe\GscManager\Services\GscDiscoveryService;

class DiscoverGscSitesCommand extends Command
{
    protected $signature = 'gsc:discover {--credential= : Specific JSON filename}';

    protected $description = 'Scan service account JSON files and discover accessible Google Search Console sites.';

    public function handle(GscCredentialScanner $scanner, GscDiscoveryService $discovery): int
    {
        $fileName = $this->option('credential') ?: null;
        $scan = $scanner->scan($fileName);
        $credential = $fileName ? $scan['credentials']->first() : null;
        if ($fileName && ! $credential) {
            $this->warn('No valid credential found for the requested JSON filename.');
            $stats = ['credentials_processed' => 0, 'sites_created' => 0, 'sites_updated' => 0, 'errors' => 0];
        } else {
            $stats = $discovery->discover($credential);
        }

        $this->table(['Metric', 'Value'], [
            ['credentials scanned', $scan['scanned']],
            ['credentials processed', $stats['credentials_processed']],
            ['sites created', $stats['sites_created']],
            ['sites updated', $stats['sites_updated']],
            ['errors', $scan['errors'] + $stats['errors']],
        ]);

        return ($scan['errors'] + $stats['errors']) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
