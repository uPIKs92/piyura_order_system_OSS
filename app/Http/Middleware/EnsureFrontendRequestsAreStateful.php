<?php

namespace App\Http\Middleware;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful as SanctumMiddleware;
use Laravel\Sanctum\Sanctum;

class EnsureFrontendRequestsAreStateful extends SanctumMiddleware
{
  /**
   * Sanctum only checks Referer/Origin by default. Same-host SPA calls and
   * DevTools requests to /api/* often omit those headers, so also match Host.
   */
  public static function fromFrontend($request): bool
  {
    if (parent::fromFrontend($request)) {
      return true;
    }

    $host = $request->getHttpHost();
    if ($host === '') {
      return false;
    }

    $stateful = array_filter(config('sanctum.stateful', []));

    return Str::is(
      Collection::make($stateful)->map(function ($uri) use ($request) {
        if ($uri === Sanctum::$currentRequestHostPlaceholder) {
          $uri = $request->getHttpHost();
        }

        return trim($uri).'/*';
      })->all(),
      $host.'/'
    );
  }
}
