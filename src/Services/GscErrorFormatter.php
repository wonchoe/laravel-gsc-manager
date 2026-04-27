<?php

namespace Wonchoe\GscManager\Services;

use Google\Service\Exception as GoogleServiceException;
use Throwable;
use Wonchoe\GscManager\Support\GscSafeJson;

class GscErrorFormatter
{
    /**
     * @return array<string, mixed>
     */
    public function format(Throwable $exception): array
    {
        $payload = [
            'class' => $exception::class,
            'code' => $exception->getCode(),
            'message' => $this->redactString($exception->getMessage()),
        ];

        if ($exception instanceof GoogleServiceException) {
            $payload['errors'] = GscSafeJson::redact($exception->getErrors());
        }

        return $payload;
    }

    public function redactString(string $value): string
    {
        $value = preg_replace('/-----BEGIN PRIVATE KEY-----.*?-----END PRIVATE KEY-----/s', '[redacted-private-key]', $value) ?? $value;
        $value = preg_replace('/"private_key"\s*:\s*"[^"]+"/', '"private_key":"[redacted]"', $value) ?? $value;
        $credentialsPath = (string) config('gsc-manager.credentials_path', '');

        if ($credentialsPath !== '') {
            $value = str_replace($credentialsPath, '[credential-path]', $value);
        }

        return $value;
    }
}
