<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('webhook:process')->everyMinute()->withoutOverlapping();
Schedule::command('registrations:expire-cash')->everyMinute()->withoutOverlapping();
