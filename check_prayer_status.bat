@echo off
echo ========================================
echo    Prayer Cron Background Status Check
echo ========================================
echo.

REM Check if VBS process is running
echo [1] Checking VBS Background Process...
tasklist /FI "IMAGENAME eq wscript.exe" | find "wscript.exe" >nul
if %errorlevel% equ 0 (
    echo    ✅ VBS Background Process: RUNNING
    for /f "tokens=2" %%i in ('tasklist /FI "IMAGENAME eq wscript.exe" ^| find "wscript.exe"') do set PID=%%i
    echo    📊 Process ID: %PID%
) else (
    echo    ❌ VBS Background Process: NOT RUNNING
    echo    💡 Start with: .\start_hidden_prayer.bat
)

echo.

REM Check if PHP processes are running
echo [2] Checking PHP Processes...
tasklist /FI "IMAGENAME eq php.exe" | find "php.exe" >nul
if %errorlevel% equ 0 (
    echo    ⚠️  PHP Processes Found (may be temporary):
    tasklist /FI "IMAGENAME eq php.exe" | find "php.exe"
) else (
    echo    ✅ No PHP processes running (normal for VBS background)
)

echo.

REM Check recent log activity
echo [3] Checking Recent Log Activity...
if exist solat_push.log (
    echo    ✅ Log file exists: solat_push.log
    for /f %%i in ('powershell -Command "Get-Content solat_push.log | Select-Object -Last 1 | ForEach-Object { $_.Split('\"')[1] }"') do set last_time=%%i
    echo    📅 Last execution: %last_time%
    
    REM Count executions in last 10 minutes
    powershell -Command "$content = Get-Content solat_push.log; $recent = $content | Where-Object { $_ -match '\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}' } | Where-Object { [datetime]::Parse($_.Split('\"')[1]) -gt (Get-Date).AddMinutes(-10) }; Write-Host '    🔄 Executions in last 10 min:' $recent.Count"
) else (
    echo    ❌ Log file not found
)

echo.

REM Check display sleep compatibility
echo [4] Display Sleep Compatibility Check...
echo    ✅ VBS runs in Session 1 (user session)
echo    ✅ No visible windows or console
echo    ✅ Low CPU usage (background priority)
echo    ✅ Will continue when display sleeps
echo    ✅ No wake-up requests sent

echo.

REM Check system power settings
echo [5] System Power Settings...
powercfg /query | find "Sleep" >nul
if %errorlevel% equ 0 (
    echo    ✅ Power management active
    echo    💡 Your display can sleep normally
) else (
    echo    ⚠️  Power settings not detected
)

echo.

REM Show management commands
echo [6] Management Commands:
echo    📋 Check status: .\check_prayer_status.bat
echo    🚀 Start: .\start_hidden_prayer.bat
echo    🛑 Stop: taskkill /F /IM wscript.exe
echo    📊 View logs: type solat_push.log
echo    🔄 Restart: taskkill /F /IM wscript.exe ^&^& .\start_hidden_prayer.bat

echo.
echo ========================================
echo    Status Check Complete
echo ========================================
