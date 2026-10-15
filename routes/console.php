<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('attendance:generate-automatic')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30);

Schedule::command('biometric:check-connections')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();
