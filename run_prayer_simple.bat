@echo off
title PrayerCron
echo Starting Prayer Cron (Simple Version)...
echo This will run continuously and log to prayer_cron.log
echo Press Ctrl+C to stop

:loop
php prayer_cron.php >> prayer_cron.log 2>&1
timeout /t 60 /nobreak >nul
goto loop

