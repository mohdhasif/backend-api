# Windows Setup Guide for Prayer Cron Job

## Method 1: Manual Task Scheduler Setup

### Step 1: Open Task Scheduler
1. Press `Win + R`, type `taskschd.msc`, and press Enter
2. Or search for "Task Scheduler" in Start menu

### Step 2: Create Basic Task
1. In Task Scheduler, click "Create Basic Task" (right panel)
2. Name: `PrayerPushCron`
3. Description: `Runs prayer push notifications every minute`
4. Click "Next"

### Step 3: Set Trigger
1. Select "Daily"
2. Click "Next"
3. Set start time to current time
4. Click "Next"

### Step 4: Set Action
1. Select "Start a program"
2. Click "Next"
3. Program/script: `C:\backend-api\run_prayer_cron.bat`
4. Start in: `C:\backend-api`
5. Click "Next"

### Step 5: Finish and Configure
1. Check "Open properties dialog"
2. Click "Finish"

### Step 6: Advanced Settings
1. In Properties dialog, go to "Triggers" tab
2. Select the trigger and click "Edit"
3. Check "Repeat task every: 1 minute"
4. Set "for a duration of: 1 day"
5. Click "OK"

### Step 7: General Settings
1. Go to "General" tab
2. Check "Run whether user is logged on or not"
3. Check "Run with highest privileges"
4. Click "OK"

## Method 2: Run as Administrator

If you want to use the PowerShell script:

1. Right-click on PowerShell and select "Run as administrator"
2. Navigate to your project directory: `cd C:\backend-api`
3. Run: `powershell -ExecutionPolicy Bypass -File setup_prayer_cron.ps1`

## Method 3: Simple Batch File Loop (For Testing)

Create a file called `run_continuous.bat`:

```batch
@echo off
:loop
php cron_prayer_push.php >> solat_push.log 2>&1
timeout /t 60 /nobreak > nul
goto loop
```

Run this for testing (will run every 60 seconds).

## Verification

1. Check if task is running: `Get-ScheduledTask -TaskName "PrayerPushCron"`
2. Check logs: `type solat_push.log`
3. Test manually: `php cron_prayer_push.php`

## Troubleshooting

- **Permission denied**: Run PowerShell as Administrator
- **PHP not found**: Add PHP to your PATH or use full path
- **Database connection**: Make sure your `db.php` is configured correctly
- **OneSignal errors**: Check your API keys in the script
