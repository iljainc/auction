<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cron schedule для команд аукциона
// Schedule::command('sendMsgToAdmin')->everyMinute()->withoutOverlapping();
// Schedule::command('publish')->everyMinute()->withoutOverlapping();
// Schedule::command('temp-files:clean')->hourly();
