$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $PSScriptRoot
$runtime = Join-Path $project '.runtime'
$recordPath = Join-Path $runtime 'scheduler-process.json'
if (-not (Test-Path -LiteralPath $recordPath)) { return }
$record = Get-Content -LiteralPath $recordPath -Raw | ConvertFrom-Json
$tracked = Get-Process -Id $record.id -ErrorAction SilentlyContinue
if ($tracked -and $tracked.Path -eq $record.executable -and $tracked.StartTime.ToUniversalTime().Ticks.ToString() -eq $record.startTicks) {
    Set-Content -LiteralPath (Join-Path $runtime 'scheduler.stop') -Value 'stop' -Encoding Ascii
    if (-not $tracked.WaitForExit(45000)) { throw 'Scheduler sedang menyelesaikan tugas. Tunggu sebentar, lalu jalankan stop kembali.' }
}
Remove-Item -LiteralPath $recordPath
Write-Host 'Scheduler proyek berhenti.'
