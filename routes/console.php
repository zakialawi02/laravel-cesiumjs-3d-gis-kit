<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('geodata:cleanup-stale')->everyThirtyMinutes()->withoutOverlapping();
