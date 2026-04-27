<?php

namespace Wonchoe\GscManager\Console;

use Illuminate\Console\Command;
use Wonchoe\GscManager\Services\GscAccessCheckService;
use Wonchoe\GscManager\Services\GscCredentialScanner;

class CheckGscAccessCommand extends Command
{
    protected $signature = 'gsc:access-check {--credential= : Specific JSON filename}';

    protected $description = 'Check current Search Console access and mark missing sites.';

    public function handle(GscCredentialScanner $scanner, GscAccessCheckService $access): int
    {
        $fileName = $this->option('credential') ?: null;
        $scan = $scanner->scan($fileName);
        $credential = $fileName ? $scan['credentials']->first() : null;
        if ($fileName && ! $credential) {
            $this->warn('No valid credential found for the requested JSON filename.');
            $stats = ['credentials_processed' => 0, 'missing_access' => 0, 'discovered' => 0, 'errors' => 0];
        } else {
            $stats = $access->check($credential);
        }

        $this->table(['Metric', 'Value'], [
            ['credentials scanned', $scan['scanned']],
            ['credentials processed', $stats['credentials_processed']],
            ['missing access', $stats['missing_access']],
            ['new discovered', $stats['discovered']],
            ['errors', $scan['errors'] + $stats['errors']],
        ]);

        return ($scan['errors'] + $stats['errors']) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
