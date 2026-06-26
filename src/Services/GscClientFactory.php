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

        $credential = null;
        try {
            $credential = \Wonchoe\GscManager\Models\GscCredential::where('file_path', $credentialPath)->first();
        } catch (\Throwable $e) {
        }

        if ($credential && ($credential->auth_type ?? null) === 'oauth') {
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->setScopes([$this->scopeFor($scopeMode)]);
            $client->setAccessType('offline');

            $tokenData = $credential->token_data;
            if ($tokenData) {
                $client->setAccessToken($tokenData);
            }

            if ($client->isAccessTokenExpired()) {
                $refreshToken = $client->getRefreshToken();
                if ($refreshToken) {
                    try {
                        $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
                        if (isset($newToken['error'])) {
                            $errorMsg = 'OAuth token refresh failed: ' . ($newToken['error_description'] ?? $newToken['error']);
                            $credential->update([
                                'active' => false,
                                'last_error' => ['message' => $errorMsg, 'code' => $newToken['error']],
                            ]);
                            throw new \Exception($errorMsg);
                        } else {
                            $updatedToken = array_merge($tokenData ?? [], $newToken);
                            $client->setAccessToken($updatedToken);
                            $credential->update([
                                'token_data' => $updatedToken,
                                'last_error' => null,
                            ]);
                        }
                    } catch (\Throwable $ex) {
                        $credential->update([
                            'active' => false,
                            'last_error' => ['message' => 'Exception during token refresh: ' . $ex->getMessage()],
                        ]);
                        throw $ex;
                    }
                } else {
                    $credential->update([
                        'active' => false,
                        'last_error' => ['message' => 'Missing refresh token. Re-authorization required.'],
                    ]);
                    throw new \Exception('Missing refresh token. Re-authorization required.');
                }
            }
        } else {
            $client->setAuthConfig($credentialPath);
            $client->setScopes([$this->scopeFor($scopeMode)]);
            $client->setAccessType('offline');
        }

        return $client;
    }

    public function makeIndexingClient(string $credentialPath): Client
    {
        $client = new Client();
        $client->setApplicationName('Laravel GSC Manager Indexing');

        $credential = null;
        try {
            $credential = \Wonchoe\GscManager\Models\GscCredential::where('file_path', $credentialPath)->first();
        } catch (\Throwable $e) {
        }

        if ($credential && ($credential->auth_type ?? null) === 'oauth') {
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->setScopes([(string) config('gsc-manager.scopes.indexing')]);
            $client->setAccessType('offline');

            $tokenData = $credential->token_data;
            if ($tokenData) {
                $client->setAccessToken($tokenData);
            }

            if ($client->isAccessTokenExpired()) {
                $refreshToken = $client->getRefreshToken();
                if ($refreshToken) {
                    try {
                        $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
                        if (isset($newToken['error'])) {
                            $errorMsg = 'OAuth token refresh failed: ' . ($newToken['error_description'] ?? $newToken['error']);
                            $credential->update(['active' => false, 'last_error' => ['message' => $errorMsg, 'code' => $newToken['error']]]);
                            throw new \Exception($errorMsg);
                        }
                        $updatedToken = array_merge($tokenData ?? [], $newToken);
                        $client->setAccessToken($updatedToken);
                        $credential->update(['token_data' => $updatedToken, 'last_error' => null]);
                    } catch (\Throwable $ex) {
                        $credential->update(['active' => false, 'last_error' => ['message' => 'Exception during token refresh: ' . $ex->getMessage()]]);
                        throw $ex;
                    }
                } else {
                    $credential->update(['active' => false, 'last_error' => ['message' => 'Missing refresh token. Re-authorization required.']]);
                    throw new \Exception('Missing refresh token. Re-authorization required.');
                }
            }
        } else {
            $client->setAuthConfig($credentialPath);
            $client->setScopes([(string) config('gsc-manager.scopes.indexing')]);
        }

        return $client;
    }

    private function scopeFor(string $scopeMode): string
    {
        $scopes = config('gsc-manager.scopes', []);

        return (string) ($scopes[$scopeMode] ?? $scopes[config('gsc-manager.default_scope', 'readonly')] ?? $scopes['readonly']);
    }
}
