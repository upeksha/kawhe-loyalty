<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('wallet:cleanup-assets')->dailyAt('03:30')->withoutOverlapping();

// Production maintenance schedules (run with cron: * * * * * php artisan schedule:run)
Schedule::command('queue:prune-failed --hours=168')->dailyAt('02:10');
Schedule::command('queue:prune-batches --hours=168')->dailyAt('02:20');
