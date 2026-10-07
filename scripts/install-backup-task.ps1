param(
    [Parameter(Mandatory = $true)] [string] $PhpPath,
    [string] $BackupDirectory = 'C:\laragon\backups\helpdeskcrmc',
    [ValidateRange(1,168)] [int] $IntervalHours = 4,
    [ValidateRange(1,365)] [int] $Keep = 14
)
$ErrorActionPreference = 'Stop'
$workspace = Split-Path -Parent $PSScriptRoot
$script = Join-Path $PSScriptRoot 'backup.php'
$PhpPath = (Resolve-Path -LiteralPath $PhpPath).Path
$BackupDirectory = [System.IO.Path]::GetFullPath($BackupDirectory).TrimEnd('\','/')
$webRoot = [System.IO.Path]::GetFullPath((Split-Path -Parent $workspace)).TrimEnd('\','/')
if ($BackupDirectory -eq $webRoot -or $BackupDirectory.StartsWith($webRoot + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'Choose a backup directory outside the web document root.'
}
if ($BackupDirectory.Contains('"') -or $PhpPath.Contains('"') -or $script.Contains('"')) { throw 'Invalid backup path.' }
$name = 'HelpdeskCRMC Automatic Backup'
$existing = Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue
if ($existing -and ($existing.Actions.Execute -ne $PhpPath -or $existing.Actions.Arguments -notlike ('*"' + $script + '"*'))) {
    throw 'An unrelated scheduled task uses this name; it was left unchanged.'
}
New-Item -ItemType Directory -Path $BackupDirectory -Force | Out-Null
$item = Get-Item -LiteralPath $BackupDirectory
if ($item.Attributes -band [System.IO.FileAttributes]::ReparsePoint) { throw 'Backup directory cannot be a junction or symlink.' }
$user = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
$acl = New-Object System.Security.AccessControl.DirectorySecurity
$acl.SetAccessRuleProtection($true, $false)
foreach ($identity in @([System.Security.Principal.WindowsIdentity]::GetCurrent().User,
    (New-Object System.Security.Principal.SecurityIdentifier('S-1-5-18')),
    (New-Object System.Security.Principal.SecurityIdentifier('S-1-5-32-544')))) {
    $rule = New-Object System.Security.AccessControl.FileSystemAccessRule($identity, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
    $acl.AddAccessRule($rule)
}
Set-Acl -LiteralPath $BackupDirectory -AclObject $acl
$arguments = '"' + $script + '" --output="' + $BackupDirectory + '" --keep=' + $Keep + ' --if-due=' + $IntervalHours
$action = New-ScheduledTaskAction -Execute $PhpPath -Argument $arguments -WorkingDirectory $workspace
$triggers = @(
    (New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Hours $IntervalHours)),
    (New-ScheduledTaskTrigger -AtLogOn -User $user)
)
$principal = New-ScheduledTaskPrincipal -UserId $user -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -MultipleInstances IgnoreNew -RestartCount 4 -RestartInterval (New-TimeSpan -Minutes 15) -ExecutionTimeLimit (New-TimeSpan -Minutes 30)
Register-ScheduledTask -TaskName $name -Action $action -Trigger $triggers -Principal $principal -Settings $settings `
    -Description 'Backs up HelpdeskCRMC MySQL data and registered attachments locally; retains recent verified archives.' -Force | Out-Null
Start-ScheduledTask -TaskName $name
Write-Output "Automatic backup installed: every $IntervalHours hours while signed in; keeps $Keep archives."
Write-Output "Backup directory: $BackupDirectory"
