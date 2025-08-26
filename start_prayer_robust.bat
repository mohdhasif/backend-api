@echo off
echo Starting Robust Prayer Cron Service...
echo This will auto-restart on failures and run continuously
echo Press Ctrl+C to stop
echo.

cd /d "C:\backend-api"

echo Starting VBScript service...
start /min wscript.exe prayer_cron_robust.vbs

echo.
echo Prayer cron service started in background!
echo Check prayer_cron.log for status updates
echo Check prayer_cron_error.log for any errors
echo.
echo To stop the service, run: taskkill /f /im wscript.exe
pause
