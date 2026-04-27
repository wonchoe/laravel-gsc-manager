<?php

namespace Wonchoe\GscManager\Services;

use Google\Service\Indexing;
use Google\Service\Indexing\UrlNotification;

class GscIndexingApiService
{
    public function __construct(private readonly GscClientFactory $clients)
    {
    }

    /**
     * @param array<int, string> $contentTypes
     * @return array<string, mixed>
     */
    public function publish(string $credentialPath, string $url, string $notificationType, array $contentTypes = []): array
    {
        if (! (bool) config('gsc-manager.indexing_api.enabled', false)) {
            throw new \RuntimeException('Indexing API is disabled by config.');
        }

        if ((bool) config('gsc-manager.indexing_api.allowed_content_only', true)) {
            $allowed = (array) config('gsc-manager.indexing_api.allowed_types', ['JobPosting', 'BroadcastEvent']);
            if (array_intersect($contentTypes, $allowed) === []) {
                throw new \RuntimeException('Indexing API is restricted to allowed content types.');
            }
        }

        if (! in_array($notificationType, ['URL_UPDATED', 'URL_DELETED'], true)) {
            throw new \InvalidArgumentException('notificationType must be URL_UPDATED or URL_DELETED.');
        }

        $service = new Indexing($this->clients->makeIndexingClient($credentialPath));
        $response = $service->urlNotifications->publish(new UrlNotification([
            'url' => $url,
            'type' => $notificationType,
        ]));

        return json_decode(json_encode($response->toSimpleObject()), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(string $credentialPath, string $url): array
    {
        if (! (bool) config('gsc-manager.indexing_api.enabled', false)) {
            throw new \RuntimeException('Indexing API is disabled by config.');
        }

        $service = new Indexing($this->clients->makeIndexingClient($credentialPath));
        $response = $service->urlNotifications->getMetadata(['url' => $url]);

        return json_decode(json_encode($response->toSimpleObject()), true, 512, JSON_THROW_ON_ERROR);
    }
}
