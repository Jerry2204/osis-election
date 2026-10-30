$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $PSScriptRoot
Push-Location $project
try {
    php artisan schedule:run --no-interaction
    exit $LASTEXITCODE
} finally { Pop-Location }
