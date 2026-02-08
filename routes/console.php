<?php

use Illuminate\Support\Facades\Schedule;

// Refresh RSS feeds every 15 minutes
Schedule::command('app:refresh-feeds')->everyFifteenMinutes();

// Generate recaps hourly (command internally checks if it's the right time)
Schedule::command('app:generate-recap daily')->hourly();
Schedule::command('app:generate-recap weekly')->hourly();
Schedule::command('app:generate-recap monthly')->hourly();
