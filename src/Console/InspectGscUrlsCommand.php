<?php

namespace Wonchoe\GscManager\Console;

use Illuminate\Console\Command;
use Wonchoe\GscManager\Models\GscSite;
use Wonchoe\GscManager\Services\GscUrlInspectionService;

class InspectGscUrlsCommand extends Command
{
    protected $signature = 'gsc:inspect-urls
        {--site= : site_url}
        {--file= : path to file with URLs}
        {--url=* : one or multiple URLs}
        {--language=en-US}';

    protected $description = 'Inspect URLs for an approved active GSC site.';

    public function handle(GscUrlInspectionService $inspection): int
    {
        if (! $this->option('site')) {
            $this->error('--site is required.');
            return self::FAILURE;
        }

        $site = GscSite::query()
            ->with('credential')
            ->where('site_url', $this->option('site'))
            ->where('status', 'approved')
            ->where('active', true)
            ->first();

        if (! $site) {
            $this->error('Approved active site was not found.');
            return self::FAILURE;
        }

        $urls = collect((array) $this->option('url'));

        if ($this->option('file')) {
            $fileUrls = file((string) $this->option('file'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $urls = $urls->merge($fileUrls);
        }

        $summary = ['inspected' => 0, 'errors' => 0];

        foreach ($urls->map(fn ($url): string => trim((string) $url))->filter()->unique() as $url) {
            $result = $inspection->inspect($site, $url, (string) $this->option('language'));
            $summary['inspected']++;
            $summary['errors'] += $result->last_error ? 1 : 0;
        }

        $this->table(['Metric', 'Value'], collect($summary)->map(fn ($value, $key): array => [$key, $value])->values()->all());

        return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
