@echo off
title PrayerCron
echo Starting Prayer Cron (Continuous Mode)...
echo This will run every minute and log to prayer_cron.log
echo Press Ctrl+C to stop
echo.

:loop
php prayer_cron.php >> prayer_cron.log 2>&1
timeout /t 60 /nobreak >nul
goto loop

