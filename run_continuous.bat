@echo off
echo Starting Prayer Cron Job (Continuous Mode)
echo Press Ctrl+C to stop
echo.

:loop
echo [%date% %time%] Running prayer cron...
php cron_prayer_push.php >> solat_push.log 2>&1
echo [%date% %time%] Completed. Waiting 60 seconds...
timeout /t 60 /nobreak > nul
goto loop
