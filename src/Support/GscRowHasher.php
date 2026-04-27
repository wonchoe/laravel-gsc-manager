<?php

namespace Wonchoe\GscManager\Support;

class GscRowHasher
{
    /**
     * @param array<int|string, mixed> $parts
     */
    public static function make(array $parts): string
    {
        $normalized = [];

        foreach ($parts as $key => $value) {
            if ($value === null) {
                $normalized[$key] = '__null__';
                continue;
            }

            if (is_bool($value)) {
                $normalized[$key] = $value ? 'true' : 'false';
                continue;
            }

            $stringValue = trim((string) $value);
            $keyString = strtolower((string) $key);
            $normalized[$key] = in_array($keyString, ['url', 'page', 'query', 'inspection_url'], true)
                ? $stringValue
                : strtolower($stringValue);
        }

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
