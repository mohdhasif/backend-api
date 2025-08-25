' Prayer Cron Hidden Script
' This runs the PHP script every minute completely hidden

Set objShell = CreateObject("WScript.Shell")
Set objFSO = CreateObject("Scripting.FileSystemObject")

' Get the script directory
strScriptDir = objFSO.GetParentFolderName(WScript.ScriptFullName)
strPhpScript = strScriptDir & "\prayer_cron.php"
strLogFile = strScriptDir & "\prayer_cron.log"

' Main loop
Do
    ' Run PHP script and append to log
    strCommand = "php """ & strPhpScript & """ >> """ & strLogFile & """ 2>&1"
    objShell.Run strCommand, 0, False
    
    ' Wait 60 seconds
    WScript.Sleep 60000
Loop
