param([string]$ApacheBase = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16')
$ErrorActionPreference = 'Stop'
& (Join-Path $PSScriptRoot 'stop-scheduler.ps1')
$project = Split-Path -Parent $PSScriptRoot
$configuration = Join-Path $project '.runtime/httpd.conf'
if (Test-Path -LiteralPath $configuration) {
    & "$ApacheBase\bin\httpd.exe" -k shutdown -f $configuration
    if ($LASTEXITCODE -ne 0) {
        # Standalone Apache is not installed as a Windows service. Only stop
        # the exact process recorded for this project's configuration.
        $pidPath = Join-Path $project '.runtime/httpd.pid'
        $recordPath = Join-Path $project '.runtime/apache-process.id'
        if ((Test-Path -LiteralPath $pidPath) -and (Test-Path -LiteralPath $recordPath)) {
            $apacheRootId = [int](Get-Content -LiteralPath $pidPath)
            $recordedId = [int](Get-Content -LiteralPath $recordPath)
            $tracked = Get-Process -Id $apacheRootId -ErrorAction SilentlyContinue
            if ($tracked) {
                if ($apacheRootId -ne $recordedId -or $tracked.Path -ne (Join-Path $ApacheBase 'bin\httpd.exe')) { throw 'Identitas proses Apache proyek tidak sesuai; layanan tidak dihentikan.' }
                Stop-Process -Id $apacheRootId -ErrorAction Stop
                if (-not $tracked.WaitForExit(5000)) { throw 'Apache belum berhenti. Database tetap berjalan.' }
            }
        }
    }
}
# Ask MySQL to shut down cleanly using its saved local root configuration.
Push-Location $project
try { php scripts/stop-mysql.php } finally { Pop-Location }
