param(
    [Parameter(Mandatory = $true)] [string] $PhpPath
)
$ErrorActionPreference = 'Stop'
$workspace = Split-Path -Parent $PSScriptRoot
$worker = Join-Path $PSScriptRoot 'notification-worker.php'
$launcher = Join-Path $PSScriptRoot 'start-notification-worker.ps1'
if (!(Test-Path -LiteralPath $PhpPath -PathType Leaf)) { throw 'PHP executable not found.' }
$action = New-ScheduledTaskAction -Execute (Join-Path $PSHOME 'powershell.exe') -Argument ('-NoProfile -NonInteractive -WindowStyle Hidden -File "' + $launcher + '" -PhpPath "' + $PhpPath + '"') -WorkingDirectory $workspace
$user = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $user
$principal = New-ScheduledTaskPrincipal -UserId $user -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit ([TimeSpan]::Zero) -MultipleInstances IgnoreNew
$name = 'HelpdeskCRMC Notification Delivery'
$existing = Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue
if ($existing -and $existing.Actions.Arguments -notlike ('*' + $worker + '*') -and $existing.Actions.Arguments -notlike ('*' + $launcher + '*')) {
    throw 'An unrelated scheduled task uses this name; it was left unchanged.'
}
Register-ScheduledTask -TaskName $name -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description 'Delivers queued HelpdeskCRMC email and browser notifications.' -Force | Out-Null
Start-ScheduledTask -TaskName $name
Write-Output 'Notification delivery task installed and started.'
