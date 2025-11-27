<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command('devices:sync-time')->hourly();
