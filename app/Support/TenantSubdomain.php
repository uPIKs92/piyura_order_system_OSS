<?php

namespace App\Support;

class TenantSubdomain
{
    public static function loginUrlForSlug(string $slug): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        return "{$appUrl}/login";
    }
}
