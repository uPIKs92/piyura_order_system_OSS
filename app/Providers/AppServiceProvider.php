<?php

namespace App\Providers;

use App\Http\Middleware\PreventRequestsDuringMaintenanceWithIpWhitelist;
use App\Listeners\RecordBackupInDatabase;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\Backup\Events\BackupWasSuccessful;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            PreventRequestsDuringMaintenance::class,
            PreventRequestsDuringMaintenanceWithIpWhitelist::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(BackupWasSuccessful::class, RecordBackupInDatabase::class);

        if (! $this->app->runningInConsole()) {
            $request = $this->app->make('request');
            $host = strtolower($request->getHost());
            $port = (int) $request->server('SERVER_PORT');

            // Local nginx often listens on :8084; include that port in generated URLs.
            // Production reverse proxies (Cloudflare → origin :8084) must NOT leak the
            // origin port into public asset URLs or the SPA stays blank.
            $isLocalHost = $host === 'localhost'
                || $host === '127.0.0.1'
                || str_ends_with($host, '.localhost');

            if ($isLocalHost && $port > 0 && ! in_array($port, [80, 443], true)) {
                URL::forceRootUrl(sprintf(
                    '%s://%s:%d',
                    $request->getScheme(),
                    $host,
                    $port
                ));
            } elseif (! $isLocalHost) {
                $scheme = $this->app->isProduction() ? 'https' : $request->getScheme();
                URL::forceRootUrl(sprintf('%s://%s', $scheme, $host));
                if ($scheme === 'https') {
                    URL::forceScheme('https');
                }
            }
        }

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip().':'.$request->input('email'));
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip().':'.$request->input('email'));
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('service-webhook', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}
