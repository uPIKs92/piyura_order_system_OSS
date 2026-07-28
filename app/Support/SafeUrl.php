<?php

namespace App\Support;

class SafeUrl
{
    public static function isAllowed(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        if ($scheme === 'http' && ! app()->environment(['local', 'testing'])) {
            return false;
        }

        return true;
    }

    public static function sanitize(?string $url): ?string
    {
        return self::isAllowed($url) ? $url : null;
    }
}
