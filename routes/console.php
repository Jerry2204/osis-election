<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('election:backup')->everyFifteenMinutes()->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/backup.log'));
Schedule::command('election:purge-sessions')->everyMinute()->withoutOverlapping();
Schedule::command('election:reconcile')->everyMinute()->withoutOverlapping();
Schedule::command('students:purge-imports')->everyFiveMinutes()->withoutOverlapping();
