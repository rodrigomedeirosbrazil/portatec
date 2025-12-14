<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('access-codes:sync')
    ->daily()
    ->at('02:00');
