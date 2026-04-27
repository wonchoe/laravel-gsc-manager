<?php

namespace Wonchoe\GscManager\Services;

use Illuminate\Support\Carbon;
use Wonchoe\GscManager\Models\GscCredential;
use Wonchoe\GscManager\Models\GscSite;
use Wonchoe\GscManager\Models\GscSyncLog;
use Wonchoe\GscManager\Support\GscSiteUrlNormalizer;

class GscDiscoveryService
{
    public function __construct(
        private readonly GscClientFactory $clients,
        private readonly GscRateLimiter $rateLimiter,
        private readonly GscErrorFormatter $errors,
    ) {
    }

    /**
     * @return array{credentials_processed: int, sites_created: int, sites_updated: int, errors: int}
     */
    public function discover(?GscCredential $credential = null): array
    {
        $credentials = $credential ? collect([$credential]) : GscCredential::query()->where('active', true)->get();
        $stats = ['credentials_processed' => 0, 'sites_created' => 0, 'sites_updated' => 0, 'errors' => 0];

        foreach ($credentials as $item) {
            $result = $this->discoverCredential($item);
            $stats['credentials_processed']++;
            $stats['sites_created'] += $result['sites_created'];
            $stats['sites_updated'] += $result['sites_updated'];
            $stats['errors'] += $result['errors'];
        }

        return $stats;
    }

    /**
     * @return array{sites_created: int, sites_updated: int, errors: int}
     */
    public function discoverCredential(GscCredential $credential): array
    {
        $started = Carbon::now();
        $stats = ['sites_created' => 0, 'sites_updated' => 0, 'errors' => 0];

        try {
            $service = $this->clients->make($credential->file_path, 'readonly');
            $response = $this->rateLimiter->retry(fn () => $service->sites->listSites());
            $entries = method_exists($response, 'getSiteEntry') ? ($response->getSiteEntry() ?: []) : [];

            foreach ($entries as $entry) {
                $siteUrl = GscSiteUrlNormalizer::normalize((string) $entry->getSiteUrl());
                $existing = GscSite::query()->where('site_url', $siteUrl)->first();
                $isNew = $existing === null;
                $autoApprove = (bool) config('gsc-manager.auto_approve_discovered_sites', false);

                $site = GscSite::updateOrCreate(
                    ['site_url' => $siteUrl],
                    [
                        'gsc_credential_id' => $credential->id,
                        'property_type' => GscSiteUrlNormalizer::propertyType($siteUrl),
                        'permission_level' => $entry->getPermissionLevel(),
                        'status' => $isNew ? ($autoApprove ? 'approved' : 'discovered') : ($existing->status === 'missing_access' ? 'discovered' : $existing->status),
                        'active' => $isNew ? $autoApprove : (bool) $existing->active,
                        'last_discovered_at' => Carbon::now(),
                        'last_error' => null,
                    ],
                );

                $isNew ? $stats['sites_created']++ : $stats['sites_updated']++;
            }

            $credential->forceFill([
                'last_discovered_at' => Carbon::now(),
                'last_error' => null,
            ])->save();

            $this->log($credential, null, 'success', $started, 'Discovery completed.', $stats);
        } catch (\Throwable $exception) {
            $stats['errors']++;
            $error = $this->errors->format($exception);
            $credential->forceFill(['last_error' => $error])->save();
            $this->log($credential, null, 'failed', $started, 'Discovery failed.', $stats, $error);
        }

        return $stats;
    }

    /**
     * @param array<string, mixed> $stats
     * @param array<string, mixed>|null $error
     */
    private function log(GscCredential $credential, ?GscSite $site, string $status, Carbon $started, string $message, array $stats, ?array $error = null): void
    {
        GscSyncLog::create([
            'gsc_credential_id' => $credential->id,
            'gsc_site_id' => $site?->id,
            'type' => 'discovery',
            'status' => $status,
            'started_at' => $started,
            'finished_at' => Carbon::now(),
            'message' => $message,
            'stats' => $stats,
            'error' => $error,
        ]);
    }
}
