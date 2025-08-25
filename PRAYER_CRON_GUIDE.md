# Prayer Cron System Guide

## Overview
This system automatically sends prayer time notifications based on user roles:
- **Admin**: All devices get notifications
- **Client/Freelancer**: Only latest device gets notifications

## Files Created

### 1. `prayer_cron.php`
- Main prayer cron script
- Checks prayer times every minute
- Sends OneSignal push notifications
- Uses role-based device filtering
- Prevents duplicate notifications

### 2. `start_prayer_cron.ps1`
- PowerShell script to start background cron
- Runs every minute automatically
- Logs to `prayer_cron.log`
- Won't block display sleep

### 3. `run_prayer_cron.bat`
- Alternative batch file to start cron
- Runs in minimized window
- Won't block display sleep

### 4. `status.bat`
- Check cron status and logs
- Test PHP script functionality
- Show prayer times

## Database Schema Used

```sql
-- Users table
CREATE TABLE `users` (
    `id` int NOT NULL AUTO_INCREMENT,
    `role` enum('admin','client','freelancer') DEFAULT 'client',
    -- other fields...
);

-- Push subscriptions
CREATE TABLE `user_push_subscriptions` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `user_id` bigint unsigned DEFAULT NULL,
    `subscription_id` varchar(64) NOT NULL,
    `install_id` varchar(64) DEFAULT NULL,
    -- other fields...
);

-- Prayer settings
CREATE TABLE `user_prayer_settings` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `user_id` bigint unsigned DEFAULT NULL,
    `install_id` varchar(64) DEFAULT NULL,
    `method` enum('GPS','JAKIM','ALADHAN') DEFAULT 'GPS',
    `latitude` decimal(9,6) DEFAULT NULL,
    `longitude` decimal(9,6) DEFAULT NULL,
    `enabled` tinyint(1) DEFAULT '1',
    -- other fields...
);

-- Notification tracking
CREATE TABLE `prayer_notifications_sent` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `user_id` bigint unsigned DEFAULT NULL,
    `install_id` varchar(64) DEFAULT NULL,
    `prayer` enum('Fajr','Dhuhr','Asr','Maghrib','Isha') NOT NULL,
    `sent_at` datetime NOT NULL,
    -- other fields...
);
```

## How It Works

### 1. Role-Based Device Filtering
```php
// For non-admin users, only process the latest device
if ($userRole !== 'admin' && $sub['id'] != $sub['latest_device_id']) {
    continue;
}
```

### 2. Prayer Time Calculation
- Uses GPS coordinates from `user_prayer_settings`
- Fetches prayer times from `api.waktusolat.app`
- Caches results to reduce API calls

### 3. Duplicate Prevention
- Checks `prayer_notifications_sent` table
- Uses `INSERT IGNORE` to prevent duplicates
- Tracks by `install_id` and prayer name

### 4. Notification Sending
- Uses OneSignal API
- Sends to specific `subscription_id`
- English notifications: "Prayer Time: Fajr"

## Commands

### Start Cron
```bash
# PowerShell method (recommended)
powershell -ExecutionPolicy Bypass -File start_prayer_cron.ps1

# Batch method
run_prayer_cron.bat
```

### Check Status
```bash
status.bat
```

### Stop Cron
```bash
# Stop PowerShell jobs
Get-Job | Stop-Job

# Stop all cmd processes (if using batch)
taskkill /F /IM cmd.exe
```

### View Logs
```bash
type prayer_cron.log
```

## Features

✅ **Role-based notifications**
- Admin: All devices get notifications
- Client/Freelancer: Only latest device

✅ **Background operation**
- Runs every minute automatically
- Won't block display sleep
- Hidden/minimized execution

✅ **Duplicate prevention**
- Tracks sent notifications
- Prevents multiple notifications per prayer

✅ **Error handling**
- Logs all activities
- Graceful error recovery
- SSL verification disabled for reliability

✅ **Caching**
- Caches prayer times by location
- Reduces API calls
- Improves performance

## Prayer Times (Example)
- **Fajr**: 06:00 AM
- **Dhuhr**: 13:18 PM
- **Asr**: 16:30 PM
- **Maghrib**: 19:23 PM
- **Isha**: 20:33 PM

## After PC Restart
Run this command to restart the cron:
```bash
powershell -ExecutionPolicy Bypass -File start_prayer_cron.ps1
```

## Troubleshooting

### Check if running
```bash
Get-Job
```

### Check logs
```bash
type prayer_cron.log
```

### Test PHP script
```bash
php prayer_cron.php
```

### Manual test
```bash
status.bat
```

## System Requirements
- PHP with cURL extension
- MySQL/MariaDB database
- Windows PowerShell
- OneSignal account configured
