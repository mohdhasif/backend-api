@echo off
echo === Prayer Cron Status ===
echo Time: %date% %time%
echo.

echo 1. Checking if cron is running:
tasklist | findstr "cmd.exe" | findstr "PrayerCron" >nul
if %errorlevel% equ 0 (
    echo    ✅ Cron is RUNNING
) else (
    echo    ❌ Cron is NOT running
)
echo.

echo 2. Checking log file:
if exist prayer_cron.log (
    for %%A in (prayer_cron.log) do set size=%%~zA
    echo    📄 File: prayer_cron.log (%size% bytes)
    
    if %size% gtr 0 (
        echo    ✅ Log has content
        echo.
        echo    📋 Last 3 log entries:
        echo    -------------------
        powershell -Command "Get-Content prayer_cron.log -Tail 3"
    ) else (
        echo    ❌ Log is empty
    )
) else (
    echo    ❌ Log file not found
)
echo.

echo === Commands ===
echo Start:  start_prayer_minimized.bat
echo Stop:   taskkill /f /im cmd.exe /fi "WINDOWTITLE eq PrayerCron*"
echo Check:  check_prayer_status.bat
echo Logs:   type prayer_cron.log
echo.
pause
