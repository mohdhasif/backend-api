@echo off
echo === Prayer Cron Status Check ===
echo Time: %date% %time%
echo.

REM Check if PowerShell jobs are running
echo 1. Checking PowerShell Jobs:
powershell -Command "Get-Job" 2>nul
if %errorlevel% equ 0 (
    echo    ✅ Jobs found - Cron is running
) else (
    echo    ❌ No jobs found - Cron is NOT running
)
echo.

REM Check log file
echo 2. Checking Log File:
if exist prayer_cron.log (
    for %%A in (prayer_cron.log) do set size=%%~zA
    echo    📄 File: prayer_cron.log (%size% bytes)
    
    REM Get last modified time
    for %%A in (prayer_cron.log) do set modified=%%~tA
    echo    🕐 Last Modified: %modified%
    
    if %size% gtr 0 (
        echo    ✅ Log file has content
        echo.
        echo    📋 Last 3 log entries:
        echo    -------------------
        powershell -Command "Get-Content prayer_cron.log -Tail 3"
    ) else (
        echo    ❌ Log file is empty
    )
) else (
    echo    ❌ Log file not found
)
echo.

REM Test PHP script
echo 3. Testing PHP Script:
php prayer_cron.php >nul 2>&1
if %errorlevel% equ 0 (
    echo    ✅ PHP script works
) else (
    echo    ❌ PHP script failed
)
echo.

echo === Commands ===
echo Start cron:  powershell -ExecutionPolicy Bypass -File start_prayer_cron.ps1
echo Stop cron:   Get-Job ^| Stop-Job
echo Check jobs:  Get-Job
echo View logs:   type prayer_cron.log
echo Live logs:   powershell -Command "Get-Content prayer_cron.log -Wait -Tail 1"
echo.

pause

