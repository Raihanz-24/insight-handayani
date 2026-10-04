<?php

use App\Console\Commands\SnapshotRatingsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler — snapshot rating otomatis
|--------------------------------------------------------------------------
| Dijalankan tiap jam; comand itu sendiri yang memutuskan tempat mana yang
| "due" (berdasarkan schedule_interval_days & schedule_hour per tempat, serta
| mode analisis `scheduled`). Jadi frekuensi pengambilan = 100% dikendalikan
| dari UI developer, bukan dari sini.
|
| Tempat dengan mode `manual`/`off` TIDAK pernah diambil oleh jadwal ini.
|
| Prasyarat server: cron `* * * * * php /path/artisan schedule:run`.
*/
Schedule::command(SnapshotRatingsCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
