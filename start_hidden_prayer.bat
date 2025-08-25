@echo off
echo Starting Prayer Cron in Hidden Background Mode...
echo This will continue running even when your display sleeps.
echo.

REM Check if already running
tasklist /FI "IMAGENAME eq wscript.exe" /FI "WINDOWTITLE eq prayer_cron_hidden.vbs" | find "wscript.exe" >nul
if %errorlevel% equ 0 (
    echo Prayer cron is already running in background.
    echo To stop it, run: taskkill /F /IM wscript.exe
    pause
    exit /b
)

REM Start the VBS script hidden
start "" /B wscript.exe prayer_cron_hidden.vbs

echo Prayer cron started in hidden background mode!
echo.
echo To check if it's running:
echo   tasklist /FI "IMAGENAME eq wscript.exe"
echo.
echo To stop it:
echo   taskkill /F /IM wscript.exe
echo.
echo To view logs:
echo   type solat_push.log
echo.
pause
