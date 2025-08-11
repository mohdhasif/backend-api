@echo off
cd /d C:\backend-api
"C:\php\php.exe" "C:\backend-api\cron_prayer_push.php" --install=INST_ID_DEVICEMU --force --window=600 --debug >> "C:\backend-api\solat_push.log" 2>>&1
