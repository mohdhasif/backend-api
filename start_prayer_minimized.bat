@echo off
echo Starting Prayer Cron in Minimized Window...
echo This will run every minute and won't block display sleep
echo.
start "PrayerCron" /MIN cmd /c "run_prayer_continuous.bat"
echo Prayer cron started in minimized window!
echo.
echo To check if it's running:
echo   tasklist | findstr "cmd.exe"
echo.
echo To stop it:
echo   taskkill /f /im cmd.exe /fi "WINDOWTITLE eq PrayerCron*"
echo.
echo To view logs:
echo   type prayer_cron.log
echo.
pause

