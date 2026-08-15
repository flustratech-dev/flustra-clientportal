<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Jadwal
|--------------------------------------------------------------------------
|
| Rekonsiliasi portal ↔ ERP. Webhook tetap jalur utamanya; ini jaring pengaman
| untuk kiriman yang tidak pernah sampai dan keputusan yang webhook-nya hilang.
|
| withoutOverlapping(): sekali jalan bisa memakan lebih dari 15 menit kalau ERP
| lambat, dan dua proses yang menarik status yang sama hanya akan saling
| menimpa.
|
*/

Schedule::command('portal:sync-status')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();
