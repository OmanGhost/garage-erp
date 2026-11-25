<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduler — add one cron entry on the server:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
*/
Schedule::command('erp:daily')->dailyAt('07:00')->timezone('Asia/Muscat')->withoutOverlapping();
Schedule::command('erp:backup')->dailyAt('02:00')->timezone('Asia/Muscat')->withoutOverlapping();
Schedule::command('erp:depreciation')->monthlyOn(1, '03:00')->timezone('Asia/Muscat');
Schedule::command('queue:prune-failed --hours=720')->weekly();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::call(fn () => \Illuminate\Support\Facades\DB::table('idempotency_keys')->where('created_at', '<', now()->subDays(2))->delete())->daily();
