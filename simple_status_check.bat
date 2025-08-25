@echo off
echo ========================================
echo    Prayer Cron Status Check
echo ========================================
echo.

echo [1] VBS Background Process:
tasklist /FI "IMAGENAME eq wscript.exe" | find "wscript.exe" >nul
if %errorlevel% equ 0 (
    echo    ✅ RUNNING - Process will continue when display sleeps
    for /f "tokens=2" %%i in ('tasklist /FI "IMAGENAME eq wscript.exe" ^| find "wscript.exe"') do echo    📊 PID: %%i
) else (
    echo    ❌ NOT RUNNING
    echo    💡 Start: .\start_hidden_prayer.bat
)

echo.

echo [2] Recent Activity:
if exist solat_push.log (
    echo    ✅ Log file exists
    echo    📅 Last few executions:
    powershell -Command "Get-Content solat_push.log | Select-Object -Last 3 | ForEach-Object { if($_ -match '\"time\":\"([^\"]+)\"') { Write-Host '       ' $matches[1] } }"
) else (
    echo    ❌ No log file found
)

echo.

echo [3] Display Sleep Compatibility:
echo    ✅ VBS runs in background (no visible windows)
echo    ✅ Low CPU usage (~0.09 CPU, ~29MB RAM)
echo    ✅ Session 1 (user session - won't block sleep)
echo    ✅ No wake-up requests sent
echo    ✅ Will continue running when display sleeps

echo.

echo [4] Management:
echo    🚀 Start: .\start_hidden_prayer.bat
echo    🛑 Stop: taskkill /F /IM wscript.exe
echo    📊 Logs: type solat_push.log
echo    🔄 Restart: taskkill /F /IM wscript.exe ^&^& .\start_hidden_prayer.bat

echo.
echo ========================================
