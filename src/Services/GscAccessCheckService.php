<?php

namespace Wonchoe\GscManager\Services;

use Illuminate\Support\Carbon;
use Wonchoe\GscManager\Models\GscCredential;
use Wonchoe\GscManager\Models\GscSite;
use Wonchoe\GscManager\Models\GscSyncLog;
use Wonchoe\GscManager\Support\GscSiteUrlNormalizer;

class GscAccessCheckService
{
    public function __construct(
        private readonly GscClientFactory $clients,
        private readonly GscRateLimiter $rateLimiter,
        private readonly GscErrorFormatter $errors,
    ) {
    }

    /**
     * @return array{credentials_processed: int, missing_access: int, discovered: int, errors: int}
     */
    public function check(?GscCredential $credential = null): array
    {
        $credentials = $credential ? collect([$credential]) : GscCredential::query()->where('active', true)->get();
        $summary = ['credentials_processed' => 0, 'missing_access' => 0, 'discovered' => 0, 'errors' => 0];

        foreach ($credentials as $item) {
            $started = Carbon::now();
            $stats = ['missing_access' => 0, 'discovered' => 0];

            try {
                $response = $this->rateLimiter->retry(fn () => $this->clients->make($item->file_path)->sites->listSites());
                $seen = collect($response->getSiteEntry() ?: [])
                    ->map(fn ($entry): string => GscSiteUrlNormalizer::normalize((string) $entry->getSiteUrl()))
                    ->values();

                GscSite::query()
                    ->where('gsc_credential_id', $item->id)
                    ->whereNotIn('site_url', $seen->all())
                    ->get()
                    ->each(function (GscSite $site) use (&$stats): void {
                        $site->forceFill(['status' => 'missing_access', 'active' => false])->save();
                        $stats['missing_access']++;
                    });

                foreach ($response->getSiteEntry() ?: [] as $entry) {
                    $siteUrl = GscSiteUrlNormalizer::normalize((string) $entry->getSiteUrl());
                    $existing = GscSite::query()->where('site_url', $siteUrl)->first();
                    if (! $existing) {
                        GscSite::create([
                            'gsc_credential_id' => $item->id,
                            'site_url' => $siteUrl,
                            'permission_level' => $entry->getPermissionLevel(),
                            'property_type' => GscSiteUrlNormalizer::propertyType($siteUrl),
                            'status' => 'discovered',
                            'active' => false,
                            'last_discovered_at' => Carbon::now(),
                        ]);
                        $stats['discovered']++;
                    } else {
                        $existing->forceFill([
                            'gsc_credential_id' => $item->id,
                            'permission_level' => $entry->getPermissionLevel(),
                            'last_discovered_at' => Carbon::now(),
                            'status' => $existing->status === 'missing_access' ? 'discovered' : $existing->status,
                        ])->save();
                    }
                }

                $summary['credentials_processed']++;
                $summary['missing_access'] += $stats['missing_access'];
                $summary['discovered'] += $stats['discovered'];
                $this->log($item, 'success', $started, 'Access check completed.', $stats);
            } catch (\Throwable $exception) {
                $summary['errors']++;
                $error = $this->errors->format($exception);
                $item->forceFill(['last_error' => $error])->save();
                $this->log($item, 'failed', $started, 'Access check failed.', $stats, $error);
            }
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $stats
     * @param array<string, mixed>|null $error
     */
    private function log(GscCredential $credential, string $status, Carbon $started, string $message, array $stats, ?array $error = null): void
    {
        GscSyncLog::create([
            'gsc_credential_id' => $credential->id,
            'type' => 'access_check',
            'status' => $status,
            'started_at' => $started,
            'finished_at' => Carbon::now(),
            'message' => $message,
            'stats' => $stats,
            'error' => $error,
        ]);
    }
}
