Option Explicit

Dim objShell, objFSO, strPath, strLogFile, strErrorLog, strScript
Dim intRestartCount, maxRestarts, restartDelay

' Configuration
strPath = "C:\backend-api"
strLogFile = strPath & "\prayer_cron.log"
strErrorLog = strPath & "\prayer_cron_error.log"
strScript = strPath & "\prayer_cron.php"
maxRestarts = 10
restartDelay = 30 ' seconds

Set objShell = CreateObject("WScript.Shell")
Set objFSO = CreateObject("Scripting.FileSystemObject")

' Change to the correct directory
objShell.CurrentDirectory = strPath

' Log startup
LogMessage "=== Prayer Cron Service Started ==="
LogMessage "Max restarts: " & maxRestarts
LogMessage "Restart delay: " & restartDelay & " seconds"
LogMessage "Script path: " & strScript

intRestartCount = 0

Do While intRestartCount < maxRestarts
    LogMessage "Starting prayer cron (attempt " & (intRestartCount + 1) & "/" & maxRestarts & ")"
    
    ' Run the PHP script
    Dim exitCode
    exitCode = objShell.Run("php " & strScript, 0, False)
    
    ' Check if script exited with error
    If exitCode <> 0 Then
        intRestartCount = intRestartCount + 1
        LogError "Script exited with code " & exitCode & " (attempt " & intRestartCount & "/" & maxRestarts & ")"
        
        If intRestartCount < maxRestarts Then
            LogMessage "Waiting " & restartDelay & " seconds before restart..."
            WScript.Sleep restartDelay * 1000
        Else
            LogError "Max restart attempts reached. Service stopping."
        End If
    Else
        ' Script exited normally, reset restart count
        intRestartCount = 0
        LogMessage "Script completed successfully, restarting immediately"
    End If
    
    ' Small delay to prevent rapid restarts
    WScript.Sleep 1000
Loop

LogMessage "=== Prayer Cron Service Stopped ==="

Sub LogMessage(message)
    Dim timestamp
    timestamp = Now()
    WriteToFile strLogFile, "[" & timestamp & "] SERVICE: " & message
End Sub

Sub LogError(message)
    Dim timestamp
    timestamp = Now()
    WriteToFile strErrorLog, "[" & timestamp & "] ERROR: " & message
    WriteToFile strLogFile, "[" & timestamp & "] ERROR: " & message
End Sub

Sub WriteToFile(filePath, content)
    Dim objFile
    Set objFile = objFSO.OpenTextFile(filePath, 8, True) ' 8 = ForAppending, True = Create if not exists
    objFile.WriteLine content
    objFile.Close
End Sub
