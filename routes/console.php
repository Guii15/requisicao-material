<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Precisa do cron do servidor chamando "php artisan schedule:run" a cada minuto (ver README).
Schedule::command('backup:fazer')->dailyAt('20:00');
