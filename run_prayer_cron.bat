@echo off
echo Starting Prayer Cron in Background...
echo This will run every minute and log to prayer_cron.log
echo The window will be minimized and won't block display sleep.
echo.

REM Check if already running
tasklist /FI "IMAGENAME eq cmd.exe" /FI "WINDOWTITLE eq PrayerCron*" | find "cmd.exe" >nul
if %errorlevel% equ 0 (
    echo Prayer cron is already running.
    echo To stop it, run: taskkill /F /IM cmd.exe
    pause
    exit /b
)

REM Start the cron in a minimized window
start "PrayerCron" /MIN cmd /c "cd /d %~dp0 && :loop && php prayer_cron.php >> prayer_cron.log 2>&1 && timeout /t 60 /nobreak >nul && goto loop"

echo Prayer cron started in background!
echo.
echo To check if it's running:
echo   tasklist /FI "IMAGENAME eq cmd.exe" /FI "WINDOWTITLE eq PrayerCron*"
echo.
echo To stop it:
echo   taskkill /F /IM cmd.exe
echo.
echo To view logs:
echo   type prayer_cron.log
pause
