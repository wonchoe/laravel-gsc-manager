<?php

namespace Wonchoe\GscManager\Support;

class GscSafeJson
{
    /**
     * @return array<string, mixed>
     */
    public static function decodeFile(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException('Unable to read JSON credential file.');
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Credential JSON must decode to an object.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function redact(array $payload): array
    {
        foreach (['private_key', 'private_key_id', 'client_secret', 'access_token', 'refresh_token'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[redacted]';
            }
        }

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::redact($value);
            }
        }

        return $payload;
    }
}
