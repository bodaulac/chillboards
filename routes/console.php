<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Order Syncing
Schedule::command('shopify:sync')->everyThirtyMinutes();
Schedule::command('walmart:orders:sync')->everyThirtyMinutes();

// Product Syncing
Schedule::command('walmart:sync')->everySixHours();
Schedule::command('teams:sync-products')->dailyAt('04:00');

// Tracking Syncing
Schedule::command('tracking:sync')->hourly();

// Trends Syncing
Schedule::command('trends:sync')->daily();
