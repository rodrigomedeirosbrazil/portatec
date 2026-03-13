<?php

use App\Jobs\RefreshTuyaTokenJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new RefreshTuyaTokenJob)->everyFiveMinutes();

Schedule::command('access-codes:sync')
    ->daily()
    ->at('02:00');

Schedule::command('bookings:sync')
    ->dailyAt('06:00')
    ->timezone('America/Sao_Paulo');
