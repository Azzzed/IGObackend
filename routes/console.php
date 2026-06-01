<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler de notificaciones push
|--------------------------------------------------------------------------
| Estos schedules solo corren si hay un worker activo ejecutando
| `php artisan schedule:work`. En Railway (sin worker) se usan en su lugar
| los endpoints POST /api/v1/cron/* llamados por cron-job.org.
| Se dejan registrados para activarlos en el futuro sin tocar código.
*/

Schedule::command('push:diario')
    ->dailyAt('07:00')
    ->timezone('America/Bogota');

Schedule::command('push:semanal')
    ->weeklyOn(1, '08:00') // 1 = lunes
    ->timezone('America/Bogota');
