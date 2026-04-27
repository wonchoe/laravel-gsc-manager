<?php

namespace Wonchoe\GscManager\Support;

class GscSiteUrlNormalizer
{
    public static function normalize(string $siteUrl): string
    {
        $siteUrl = trim($siteUrl);

        if (str_starts_with(strtolower($siteUrl), 'sc-domain:')) {
            return 'sc-domain:' . substr($siteUrl, 10);
        }

        return $siteUrl;
    }

    public static function propertyType(string $siteUrl): string
    {
        $normalized = self::normalize($siteUrl);

        if (str_starts_with($normalized, 'sc-domain:')) {
            return 'domain';
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return 'url_prefix';
        }

        return 'unknown';
    }
}
