<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Shared hosting tidak mengizinkan proses `queue:work` yang hidup terus.
| Cukup satu cron per menit di cPanel:
|   * * * * * cd /home/USER/siarpu && php artisan schedule:run >> /dev/null 2>&1
| lalu scheduler di bawah memproses antrean sampai kosong dan berhenti.
*/
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
