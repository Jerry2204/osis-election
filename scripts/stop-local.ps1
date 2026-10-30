param([string]$ApacheBase = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16')
$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $PSScriptRoot
$configuration = Join-Path $project '.runtime/httpd.conf'
if (Test-Path -LiteralPath $configuration) {
    & "$ApacheBase\bin\httpd.exe" -k shutdown -f $configuration
}
# Ask MySQL to shut down cleanly using its saved local root configuration.
Push-Location $project
try { php scripts/stop-mysql.php } finally { Pop-Location }
