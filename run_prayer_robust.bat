@echo off
title PrayerCronRobust
echo Starting Prayer Cron (Robust Version)...
echo This will auto-restart if it crashes
echo Press Ctrl+C to stop permanently
echo.

:restart
echo [%date% %time%] Starting prayer cron...
php prayer_cron.php >> prayer_cron.log 2>&1
if %errorlevel% neq 0 (
    echo [%date% %time%] Error occurred, restarting in 10 seconds...
    timeout /t 10 /nobreak >nul
    goto restart
)

timeout /t 60 /nobreak >nul
goto restart

