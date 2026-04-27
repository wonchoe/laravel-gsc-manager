<?php

namespace Wonchoe\GscManager\Services;

use Google\Client;
use Google\Service\SearchConsole;

class GscClientFactory
{
    public function make(string $credentialPath, string $scopeMode = 'readonly'): SearchConsole
    {
        return new SearchConsole($this->makeRawClient($credentialPath, $scopeMode));
    }

    public function makeRawClient(string $credentialPath, string $scopeMode = 'readonly'): Client
    {
        $client = new Client();
        $client->setApplicationName('Laravel GSC Manager');
        $client->setAuthConfig($credentialPath);
        $client->setScopes([$this->scopeFor($scopeMode)]);
        $client->setAccessType('offline');

        return $client;
    }

    public function makeIndexingClient(string $credentialPath): Client
    {
        $client = new Client();
        $client->setApplicationName('Laravel GSC Manager Indexing');
        $client->setAuthConfig($credentialPath);
        $client->setScopes([(string) config('gsc-manager.scopes.indexing')]);

        return $client;
    }

    private function scopeFor(string $scopeMode): string
    {
        $scopes = config('gsc-manager.scopes', []);

        return (string) ($scopes[$scopeMode] ?? $scopes[config('gsc-manager.default_scope', 'readonly')] ?? $scopes['readonly']);
    }
}
