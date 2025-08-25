# PowerShell script to set up Prayer Cron Job in Windows Task Scheduler

# Get the current directory
$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
$batchFile = Join-Path $scriptPath "run_prayer_cron.bat"

# Create the scheduled task
$taskName = "PrayerPushCron"
$taskDescription = "Runs prayer push notifications every minute"

# Remove existing task if it exists
Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue

# Create the task action
$action = New-ScheduledTaskAction -Execute $batchFile -WorkingDirectory $scriptPath

# Create the trigger (every minute)
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 365)

# Create the task settings
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

# Register the task
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Description $taskDescription -User "SYSTEM"

Write-Host "Prayer cron job has been set up successfully!" -ForegroundColor Green
Write-Host "Task Name: $taskName" -ForegroundColor Yellow
Write-Host "Batch File: $batchFile" -ForegroundColor Yellow
Write-Host "Log File: $scriptPath\solat_push.log" -ForegroundColor Yellow
Write-Host ""
Write-Host "To manage the task:" -ForegroundColor Cyan
Write-Host "  - Start: Start-ScheduledTask -TaskName '$taskName'" -ForegroundColor White
Write-Host "  - Stop: Stop-ScheduledTask -TaskName '$taskName'" -ForegroundColor White
Write-Host "  - Delete: Unregister-ScheduledTask -TaskName '$taskName'" -ForegroundColor White
Write-Host "  - View: Get-ScheduledTask -TaskName '$taskName'" -ForegroundColor White
