<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sheets:dispatch')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('orders:auto-cancel')->hourly()->withoutOverlapping(5);
Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('backup:clean')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('activitylog:clean-retention')->monthly()->withoutOverlapping();
