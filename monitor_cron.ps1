# Prayer Cron Monitor
# This script monitors the cron job status and activity

Write-Host "=== Prayer Cron Monitor ===" -ForegroundColor Cyan
Write-Host "Time: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" -ForegroundColor Yellow
Write-Host ""

# 1. Check if PowerShell job is running
Write-Host "1. PowerShell Job Status:" -ForegroundColor Green
$jobs = Get-Job
if ($jobs) {
    Write-Host "   ✅ Jobs running: $($jobs.Count)" -ForegroundColor Green
    $jobs | Format-Table -AutoSize
} else {
    Write-Host "   ❌ No PowerShell jobs running" -ForegroundColor Red
}
Write-Host ""

# 2. Check log file
Write-Host "2. Log File Status:" -ForegroundColor Green
$logFile = "prayer_cron.log"
if (Test-Path $logFile) {
    $logInfo = Get-Item $logFile
    $logSize = $logInfo.Length
    $lastModified = $logInfo.LastWriteTime
    $timeSinceLastUpdate = (Get-Date) - $lastModified
    
    Write-Host "   📄 File: $logFile" -ForegroundColor White
    Write-Host "   📏 Size: $logSize bytes" -ForegroundColor White
    Write-Host "   🕐 Last Modified: $lastModified" -ForegroundColor White
    Write-Host "   ⏱️  Time Since Last Update: $($timeSinceLastUpdate.TotalSeconds.ToString('F1')) seconds" -ForegroundColor White
    
    if ($timeSinceLastUpdate.TotalSeconds -lt 120) {
        Write-Host "   ✅ Log is being updated recently" -ForegroundColor Green
    } else {
        Write-Host "   ⚠️  Log hasn't been updated recently" -ForegroundColor Yellow
    }
    
    # Show last 3 log entries
    Write-Host ""
    Write-Host "   📋 Last 3 Log Entries:" -ForegroundColor Cyan
    $lastEntries = Get-Content $logFile -Tail 3
    if ($lastEntries) {
        foreach ($entry in $lastEntries) {
            Write-Host "      $entry" -ForegroundColor Gray
        }
    } else {
        Write-Host "      No log entries found" -ForegroundColor Gray
    }
} else {
    Write-Host "   ❌ Log file not found: $logFile" -ForegroundColor Red
}
Write-Host ""

# 3. Test PHP script
Write-Host "3. PHP Script Test:" -ForegroundColor Green
try {
    $output = & php prayer_cron.php 2>&1
    Write-Host "   ✅ PHP script works" -ForegroundColor Green
    Write-Host "   📊 Output: $output" -ForegroundColor Gray
} catch {
    Write-Host "   ❌ PHP script failed: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""

# 4. Check if cron should be running
Write-Host "4. Cron Status Assessment:" -ForegroundColor Green
$jobs = Get-Job
$logFile = "prayer_cron.log"

if ($jobs) {
    Write-Host "   ✅ Cron is RUNNING" -ForegroundColor Green
    Write-Host "   🎯 Status: ACTIVE" -ForegroundColor Green
} else {
    Write-Host "   ❌ Cron is NOT RUNNING" -ForegroundColor Red
    Write-Host "   🎯 Status: INACTIVE" -ForegroundColor Red
    Write-Host ""
    Write-Host "   💡 To start the cron:" -ForegroundColor Yellow
    Write-Host "      powershell -ExecutionPolicy Bypass -File start_prayer_cron.ps1" -ForegroundColor White
}
Write-Host ""

# 5. Real-time monitoring option
Write-Host "5. Real-time Monitoring:" -ForegroundColor Green
Write-Host "   💡 To watch logs in real-time:" -ForegroundColor Yellow
Write-Host "      Get-Content prayer_cron.log -Wait -Tail 1" -ForegroundColor White
Write-Host ""

# 6. Commands summary
Write-Host "=== Commands ===" -ForegroundColor Cyan
Write-Host "Start cron:   powershell -ExecutionPolicy Bypass -File start_prayer_cron.ps1" -ForegroundColor White
Write-Host "Stop cron:    Get-Job | Stop-Job" -ForegroundColor White
Write-Host "Check jobs:   Get-Job" -ForegroundColor White
Write-Host "View logs:    Get-Content prayer_cron.log -Tail 10" -ForegroundColor White
Write-Host "Live logs:    Get-Content prayer_cron.log -Wait -Tail 1" -ForegroundColor White
Write-Host ""

Read-Host "Press Enter to continue"

