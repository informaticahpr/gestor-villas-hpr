<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mora mensual: se revisa todos los dias; a partir del dia 5 aplica la del mes a las villas que deben
// (no duplica). En Railway lo ejecuta `php artisan schedule:work` (ver Dockerfile).
Schedule::command('moras:aplicar')->dailyAt('00:10')->withoutOverlapping();
