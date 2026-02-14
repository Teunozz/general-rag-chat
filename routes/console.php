<?php

use Illuminate\Support\Facades\Schedule;

// Refresh RSS and website sources every 15 minutes
Schedule::command('app:refresh-sources')->everyFifteenMinutes();

// Generate recaps hourly (command internally checks if it's the right time)
Schedule::command('app:generate-recap daily')->hourly();
Schedule::command('app:generate-recap weekly')->hourly();
Schedule::command('app:generate-recap monthly')->hourly();
