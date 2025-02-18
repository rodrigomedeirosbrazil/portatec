<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('mqtt:subscribe-worker-terminate')->everyFiveMinutes();
