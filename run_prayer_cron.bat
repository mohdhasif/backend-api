@echo off
REM Prayer Cron Job for Windows
REM Run this script every minute using Windows Task Scheduler

cd /d "C:\backend-api"

REM Run the PHP script and log output
php cron_prayer_push.php >> solat_push.log 2>&1

REM Optional: Add timestamp to log
echo [%date% %time%] Prayer cron completed >> solat_push.log
