@echo off
echo Setting up Windows Task Scheduler for Prayer Cron...
echo This will create a task that starts automatically on system startup
echo.

cd /d "C:\backend-api"

echo Creating scheduled task...
schtasks /create /tn "PrayerCronService" /tr "C:\Windows\System32\wscript.exe C:\backend-api\prayer_cron_robust.vbs" /sc onstart /ru SYSTEM /f

if %errorlevel% equ 0 (
    echo.
    echo SUCCESS: Task created successfully!
    echo The prayer cron will now start automatically when Windows starts.
    echo.
    echo To start it now, run: schtasks /run /tn "PrayerCronService"
    echo To stop it: schtasks /end /tn "PrayerCronService"
    echo To delete it: schtasks /delete /tn "PrayerCronService" /f
) else (
    echo.
    echo ERROR: Failed to create task. You may need to run as Administrator.
    echo Right-click this file and select "Run as administrator"
)

echo.
pause
