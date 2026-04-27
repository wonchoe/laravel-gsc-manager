<?php

namespace Wonchoe\GscManager\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Wonchoe\GscManager\Models\GscCredential;
use Wonchoe\GscManager\Models\GscSyncLog;
use Wonchoe\GscManager\Support\GscSafeJson;

class GscCredentialScanner
{
    public function __construct(private readonly GscErrorFormatter $errors)
    {
    }

    /**
     * @return array{credentials: \Illuminate\Support\Collection<int, GscCredential>, scanned: int, errors: int}
     */
    public function scan(?string $onlyFileName = null): array
    {
        $path = (string) config('gsc-manager.credentials_path');

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0750, true);
        }

        $files = collect(File::files($path))
            ->filter(fn ($file): bool => strtolower($file->getExtension()) === 'json')
            ->when($onlyFileName, fn ($collection) => $collection->filter(fn ($file): bool => $file->getFilename() === $onlyFileName))
            ->values();

        $credentials = collect();
        $errors = 0;

        foreach ($files as $file) {
            try {
                $json = GscSafeJson::decodeFile($file->getPathname());
                $this->assertValidCredentialJson($json);

                $credential = GscCredential::updateOrCreate(
                    ['file_name' => $file->getFilename()],
                    [
                        'file_path' => $file->getPathname(),
                        'client_email' => $json['client_email'],
                        'project_id' => $json['project_id'],
                        'scopes' => [config('gsc-manager.default_scope', 'readonly')],
                        'active' => true,
                        'last_error' => null,
                    ],
                );

                $credentials->push($credential);
            } catch (\Throwable $exception) {
                $errors++;
                $error = $this->errors->format($exception);
                $credential = GscCredential::query()
                    ->where('file_name', $file->getFilename())
                    ->first();

                $credential?->forceFill(['active' => false, 'last_error' => $error])->save();

                GscSyncLog::create([
                    'type' => 'discovery',
                    'status' => 'failed',
                    'started_at' => Carbon::now(),
                    'finished_at' => Carbon::now(),
                    'message' => 'Invalid service account JSON: ' . $file->getFilename(),
                    'stats' => ['file_name' => $file->getFilename()],
                    'error' => $error,
                ]);
            }
        }

        return ['credentials' => $credentials, 'scanned' => $files->count(), 'errors' => $errors];
    }

    /**
     * @param array<string, mixed> $json
     */
    private function assertValidCredentialJson(array $json): void
    {
        foreach (['client_email', 'private_key', 'project_id', 'token_uri'] as $key) {
            if (empty($json[$key]) || ! is_string($json[$key])) {
                throw new \InvalidArgumentException("Missing or invalid {$key} in service account JSON.");
            }
        }
    }
}
