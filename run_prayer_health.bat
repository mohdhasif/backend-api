@echo off
cd /d C:\backend-api
"C:\php\php.exe" "C:\backend-api\cron_prayer_health.php" >> "C:\backend-api\cron_prayer_health.log" 2>>&1
