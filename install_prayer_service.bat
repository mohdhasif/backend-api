@echo off
echo Installing Prayer Cron as Windows Service...
echo.

REM Check if NSSM is available
where nssm >nul 2>&1
if %errorlevel% neq 0 (
    echo NSSM not found. Installing NSSM...
    echo Please download NSSM from: https://nssm.cc/download
    echo Extract nssm.exe to this directory or add to PATH
    pause
    exit /b 1
)

REM Get current directory
set "SCRIPT_DIR=%~dp0"
set "PHP_PATH=php.exe"
set "SCRIPT_PATH=%SCRIPT_DIR%cron_prayer_push.php"

REM Install the service
echo Installing service...
nssm install PrayerPushCron "%PHP_PATH%" "%SCRIPT_PATH%"
nssm set PrayerPushCron AppDirectory "%SCRIPT_DIR%"
nssm set PrayerPushCron Description "Prayer Time Push Notification Service"
nssm set PrayerPushCron Start SERVICE_AUTO_START

REM Set up the service to run every minute using a wrapper
echo Creating wrapper script...
(
echo @echo off
echo :loop
echo php cron_prayer_push.php ^>^> solat_push.log 2^>^&1
echo timeout /t 60 /nobreak ^> nul
echo goto loop
) > "%SCRIPT_DIR%prayer_wrapper.bat"

REM Install the wrapper as service
nssm install PrayerPushCron "%SCRIPT_DIR%prayer_wrapper.bat"
nssm set PrayerPushCron AppDirectory "%SCRIPT_DIR%"
nssm set PrayerPushCron Description "Prayer Time Push Notification Service"
nssm set PrayerPushCron Start SERVICE_AUTO_START

echo.
echo Service installed successfully!
echo.
echo To manage the service:
echo   Start:   net start PrayerPushCron
echo   Stop:    net stop PrayerPushCron
echo   Remove:  nssm remove PrayerPushCron confirm
echo.
echo Starting service...
net start PrayerPushCron

echo.
echo Service is now running in the background!
echo It will continue even when your display sleeps.
echo Check logs: type solat_push.log
