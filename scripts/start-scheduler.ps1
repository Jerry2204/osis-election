param([string]$PhpBase = 'C:\laragon\bin\php\php-8.3.14')
$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $PSScriptRoot
$runtime = Join-Path $project '.runtime'
$recordPath = Join-Path $runtime 'scheduler-process.json'
$stopPath = Join-Path $runtime 'scheduler.stop'
$phpExecutable = Join-Path $PhpBase 'php.exe'
if (-not (Test-Path -LiteralPath $phpExecutable)) { throw 'PHP scheduler tidak ditemukan.' }
New-Item -ItemType Directory -Force -Path $runtime | Out-Null
if (Test-Path -LiteralPath $recordPath) {
    $record = Get-Content -LiteralPath $recordPath -Raw | ConvertFrom-Json
    $tracked = Get-Process -Id $record.id -ErrorAction SilentlyContinue
    if ($tracked -and $tracked.Path -eq $phpExecutable -and $tracked.StartTime.ToUniversalTime().Ticks.ToString() -eq $record.startTicks) {
        Write-Host 'Scheduler proyek sudah berjalan.'
        return
    }
}
if (Test-Path -LiteralPath $stopPath) { Remove-Item -LiteralPath $stopPath }
$worker = Start-Process -FilePath $phpExecutable -ArgumentList @(('"' + (Join-Path $project 'artisan') + '"'), 'election:local-scheduler') -WorkingDirectory $project -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtime 'scheduler.log') -RedirectStandardError (Join-Path $runtime 'scheduler-error.log')
@{ id = $worker.Id; startTicks = $worker.StartTime.ToUniversalTime().Ticks.ToString(); executable = $phpExecutable } | ConvertTo-Json | Set-Content -LiteralPath $recordPath -Encoding Ascii
Write-Host 'Scheduler proyek berjalan; jadwal diperiksa setiap menit.'
