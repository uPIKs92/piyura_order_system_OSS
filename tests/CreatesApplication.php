<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application. Bootstrapping here (rather than relying solely
     * on the inherited base TestCase) ensures phpunit.xml <env> values such as
     * APP_ENV=testing, APP_URL and SANCTUM_STATEFUL_DOMAINS are applied BEFORE
     * Laravel boots and reads the .env file. Without this trait, Laravel boots
     * as the production env and ignores phpunit.xml, causing the entire test
     * suite to run against production config (CSRF enforced on /api/* -> 419).
     */
    public function createApplication(): Application
    {
        // Force Laravel to load .env.testing for the test suite, with a MUTABLE
        // Dotenv repository so .env.testing values override the .env values that
        // were already loaded (immutably) when `php artisan test` booted the
        // application before PHPUnit ran.
        //
        // Background: phpunit.xml <env> directives do NOT work in this project
        // because `php artisan test` boots Laravel during artisan startup, which
        // loads .env into Laravel's default immutable Dotenv repository. By the
        // time PHPUnit's PhpHandler runs putenv(), the immutable repo already
        // holds the production values and silently ignores the test overrides.
        // A real .env.testing file + a mutable repository fixes this cleanly and
        // keeps test config version-controlled alongside the code.
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';
        putenv('APP_ENV=testing');

        // Replace Laravel's default immutable env repository with a mutable one
        // so the LoadEnvironmentVariables bootstrapper (re)loads .env.testing and
        // its values take precedence over the already-loaded .env.
        \Illuminate\Support\Env::getRepository(); // ensure the static is initialised
        $reflection = new \ReflectionClass(\Illuminate\Support\Env::class);
        $prop = $reflection->getProperty('repository');
        $prop->setValue(null, \Dotenv\Repository\RepositoryBuilder::createWithDefaultAdapters()->make());

        /** @var Application $app */
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
