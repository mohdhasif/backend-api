' Prayer Cron Service - Auto-restarting version
' This runs continuously and auto-restarts if it fails

Set objShell = CreateObject("WScript.Shell")
Set objFSO = CreateObject("Scripting.FileSystemObject")

' Get the script directory
strScriptDir = objFSO.GetParentFolderName(WScript.ScriptFullName)
strPhpScript = strScriptDir & "\prayer_cron.php"
strLogFile = strScriptDir & "\prayer_cron.log"

' Main service loop
Do
    On Error Resume Next
    
    ' Run PHP script
    strCommand = "php """ & strPhpScript & """ >> """ & strLogFile & """ 2>&1"
    intReturn = objShell.Run(strCommand, 0, True)
    
    ' Check if there was an error
    If Err.Number <> 0 Or intReturn <> 0 Then
        ' Log error and wait before restarting
        strErrorLog = strScriptDir & "\prayer_cron_error.log"
        objShell.Run "echo [" & Now() & "] Error occurred, restarting in 10 seconds... >> """ & strErrorLog & """", 0, True
        WScript.Sleep 10000 ' Wait 10 seconds before restarting
    End If
    
    On Error Goto 0
    
    ' Wait 60 seconds before next run
    WScript.Sleep 60000
Loop
