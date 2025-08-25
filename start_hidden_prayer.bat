@echo off
echo Starting Prayer Cron (Hidden Mode)...
echo This will run every minute completely hidden
echo Won't block display sleep
echo.

REM Check if already running
tasklist | findstr "wscript.exe" >nul
if %errorlevel% equ 0 (
    echo Prayer cron is already running.
    echo To stop it: taskkill /f /im wscript.exe
    pause
    exit
)

REM Start the hidden VBScript
start "" wscript.exe prayer_cron_hidden.vbs

echo Prayer cron started in hidden mode!
echo.
echo To check if it's running:
echo   tasklist | findstr "wscript.exe"
echo.
echo To stop it:
echo   taskkill /f /im wscript.exe
echo.
echo To view logs:
echo   type prayer_cron.log
echo.
pause
