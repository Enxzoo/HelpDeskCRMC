param(
    [Parameter(Mandatory = $true)] [string] $PhpPath
)
$ErrorActionPreference = 'Stop'
if (!(Test-Path -LiteralPath $PhpPath -PathType Leaf)) { throw 'PHP executable not found.' }
$opensslConfig = Join-Path (Split-Path -Parent $PhpPath) 'extras\ssl\openssl.cnf'
if (!$env:OPENSSL_CONF -and (Test-Path -LiteralPath $opensslConfig -PathType Leaf)) {
    $env:OPENSSL_CONF = $opensslConfig
}
$worker = Join-Path $PSScriptRoot 'notification-worker.php'
$process = Start-Process -FilePath $PhpPath -ArgumentList ('"' + $worker + '" --loop') -WorkingDirectory (Split-Path -Parent $PSScriptRoot) -WindowStyle Hidden -PassThru -Wait
exit $process.ExitCode
