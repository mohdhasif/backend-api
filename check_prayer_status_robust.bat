@echo off
echo === Prayer Cron Status Check ===
echo.

cd /d "C:\backend-api"

echo Checking for running processes...
tasklist /fi "imagename eq wscript.exe" /fo table

echo.
echo Checking for PHP processes...
tasklist /fi "imagename eq php.exe" /fo table

echo.
echo === Recent Log Entries ===
echo Last 5 entries from prayer_cron.log:
echo.
powershell -Command "Get-Content prayer_cron.log -Tail 5"

echo.
echo === Error Log (if any) ===
if exist prayer_cron_error.log (
    echo Last 3 entries from prayer_cron_error.log:
    echo.
    powershell -Command "Get-Content prayer_cron_error.log -Tail 3"
) else (
    echo No error log found - good!
)

echo.
echo === Service Status ===
for /f "tokens=2" %%i in ('tasklist /fi "imagename eq wscript.exe" /fo csv ^| find "wscript.exe"') do (
    echo VBScript service is RUNNING (PID: %%i)
    goto :found
)
echo VBScript service is NOT RUNNING

:found
echo.
pause
