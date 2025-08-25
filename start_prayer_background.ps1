# PowerShell script to run Prayer Cron in background
# This will continue running even when display sleeps

param(
    [switch]$Install,
    [switch]$Start,
    [switch]$Stop,
    [switch]$Status
)

$TaskName = "PrayerPushCron"
$ScriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
$BatchFile = Join-Path $ScriptPath "run_prayer_cron.bat"

function Install-Task {
    Write-Host "Installing Prayer Cron Task..." -ForegroundColor Green
    
    # Remove existing task if it exists
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue
    
    # Create the task action
    $action = New-ScheduledTaskAction -Execute $BatchFile -WorkingDirectory $ScriptPath
    
    # Create the trigger (every minute, repeat indefinitely)
    $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 365)
    
    # Create the task settings (important for background operation)
    $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -WakeToRun
    
    # Register the task with SYSTEM account (runs even when user is logged out)
    Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings -Description "Prayer Time Push Notifications" -User "SYSTEM" -RunLevel Highest
    
    Write-Host "Task installed successfully!" -ForegroundColor Green
}

function Start-Task {
    Write-Host "Starting Prayer Cron Task..." -ForegroundColor Green
    Start-ScheduledTask -TaskName $TaskName
    Write-Host "Task started!" -ForegroundColor Green
}

function Stop-Task {
    Write-Host "Stopping Prayer Cron Task..." -ForegroundColor Yellow
    Stop-ScheduledTask -TaskName $TaskName
    Write-Host "Task stopped!" -ForegroundColor Yellow
}

function Get-Status {
    try {
        $task = Get-ScheduledTask -TaskName $TaskName -ErrorAction Stop
        Write-Host "Task Status:" -ForegroundColor Cyan
        Write-Host "  Name: $($task.TaskName)" -ForegroundColor White
        Write-Host "  State: $($task.State)" -ForegroundColor White
        Write-Host "  Last Run: $($task.LastRunTime)" -ForegroundColor White
        Write-Host "  Next Run: $($task.NextRunTime)" -ForegroundColor White
    }
    catch {
        Write-Host "Task not found or not installed." -ForegroundColor Red
    }
}

# Main execution
if ($Install) {
    Install-Task
}
elseif ($Start) {
    Start-Task
}
elseif ($Stop) {
    Stop-Task
}
elseif ($Status) {
    Get-Status
}
else {
    Write-Host "Prayer Cron Background Service" -ForegroundColor Cyan
    Write-Host "Usage:" -ForegroundColor White
    Write-Host "  -Install : Install the scheduled task" -ForegroundColor White
    Write-Host "  -Start   : Start the task" -ForegroundColor White
    Write-Host "  -Stop    : Stop the task" -ForegroundColor White
    Write-Host "  -Status  : Check task status" -ForegroundColor White
    Write-Host ""
    Write-Host "Examples:" -ForegroundColor Yellow
    Write-Host "  .\start_prayer_background.ps1 -Install" -ForegroundColor White
    Write-Host "  .\start_prayer_background.ps1 -Start" -ForegroundColor White
    Write-Host "  .\start_prayer_background.ps1 -Status" -ForegroundColor White
}
