' VBScript to run Prayer Cron completely hidden in background
' This will continue running even when display sleeps

Option Explicit

Dim objShell, objFSO, scriptPath, logFile
Dim phpPath, scriptFile, command

' Get the directory where this script is located
scriptPath = CreateObject("Scripting.FileSystemObject").GetParentFolderName(WScript.ScriptFullName)
logFile = scriptPath & "\solat_push.log"

' Check if PHP is available
Set objShell = CreateObject("WScript.Shell")
phpPath = "php.exe"

' Test if PHP is available
On Error Resume Next
objShell.Run "php --version", 0, True
If Err.Number <> 0 Then
    WScript.Echo "PHP not found. Please ensure PHP is installed and in PATH."
    WScript.Quit 1
End If
On Error Goto 0

' Main loop
Do
    ' Run the prayer cron script
    command = "php """ & scriptPath & "\cron_prayer_push.php"" >> """ & logFile & """ 2>&1"
    objShell.Run command, 0, True
    
    ' Wait for 60 seconds (60000 milliseconds)
    WScript.Sleep 60000
Loop
