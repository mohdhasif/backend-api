# Prayer Cron Background Script
# This runs the PHP script every minute in the background

Write-Host "Starting Prayer Cron in Background..." -ForegroundColor Green
Write-Host "This will run every minute and log to prayer_cron.log" -ForegroundColor Yellow
Write-Host "The script runs in background and won't block display sleep." -ForegroundColor Yellow
Write-Host ""

# Get the script directory
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$phpScript = Join-Path $scriptDir "prayer_cron.php"
$logFile = Join-Path $scriptDir "prayer_cron.log"

# Check if already running
$existingProcess = Get-Process -Name "powershell" -ErrorAction SilentlyContinue | Where-Object { $_.ProcessName -eq "powershell" -and $_.MainWindowTitle -like "*PrayerCron*" }

if ($existingProcess) {
    Write-Host "Prayer cron is already running." -ForegroundColor Yellow
    Write-Host "To stop it, run: Stop-Process -Name 'powershell' -Force" -ForegroundColor Red
    Read-Host "Press Enter to continue"
    exit
}

# Start the background job
$job = Start-Job -ScriptBlock {
    param($phpScript, $logFile)
    
    while ($true) {
        try {
            $output = & php $phpScript 2>&1
            $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
            "$timestamp - $output" | Out-File -FilePath $logFile -Append -Encoding UTF8
        }
        catch {
            $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
            "$timestamp - Error: $($_.Exception.Message)" | Out-File -FilePath $logFile -Append -Encoding UTF8
        }
        
        Start-Sleep -Seconds 60
    }
} -ArgumentList $phpScript, $logFile

Write-Host "Prayer cron started in background!" -ForegroundColor Green
Write-Host ""
Write-Host "To check if it's running:" -ForegroundColor Cyan
Write-Host "  Get-Job" -ForegroundColor White
Write-Host ""
Write-Host "To stop it:" -ForegroundColor Cyan
Write-Host "  Stop-Job -Id $($job.Id)" -ForegroundColor White
Write-Host ""
Write-Host "To view logs:" -ForegroundColor Cyan
Write-Host "  Get-Content $logFile -Tail 10" -ForegroundColor White
Write-Host ""

Read-Host "Press Enter to continue"
