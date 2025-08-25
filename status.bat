@echo off
echo === Prayer Cron Status ===
echo.

REM Check log file
if exist prayer_cron.log (
    for %%A in (prayer_cron.log) do set size=%%~zA
    echo Log file: prayer_cron.log (%size% bytes)
    
    if %size% gtr 0 (
        echo ✅ Log file has content
        echo.
        echo Last log entry:
        powershell -Command "Get-Content prayer_cron.log -Tail 1"
    ) else (
        echo ❌ Log file is empty
    )
) else (
    echo ❌ Log file not found
)

echo.

REM Test PHP script
echo Testing PHP script...
php prayer_cron.php >nul 2>&1
if %errorlevel% equ 0 (
    echo ✅ PHP script works
) else (
    echo ❌ PHP script failed
)

echo.

echo Tomorrow's Prayer Times:
echo   Fajr:    06:00 AM
echo   Dhuhr:   13:18 PM
echo   Asr:     16:30 PM
echo   Maghrib: 19:23 PM
echo   Isha:    20:33 PM

echo.

echo Commands:
echo Start:  powershell -ExecutionPolicy Bypass -File start_prayer_cron.ps1
echo Stop:   Get-Job | Stop-Job
echo Logs:   type prayer_cron.log

pause
