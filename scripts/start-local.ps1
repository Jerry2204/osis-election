param(
    [string]$MySqlBase = 'C:\laragon\bin\mysql\mysql-8.0.30-winx64',
    [string]$ApacheBase = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16',
    [string]$PhpBase = 'C:\laragon\bin\php\php-8.3.14',
    [int]$Port = 8080,
    [switch]$LoopbackOnly
)
$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $PSScriptRoot
$runtime = Join-Path $project '.runtime'
New-Item -ItemType Directory -Force -Path $runtime | Out-Null
foreach ($path in @("$MySqlBase\bin\mysqld.exe", "$ApacheBase\bin\httpd.exe", "$PhpBase\php.exe")) {
    if (-not (Test-Path -LiteralPath $path)) { throw "Tidak ditemukan: $path. Sesuaikan parameter lokasi Laragon." }
}
function Test-LocalPort([int]$Number) {
    $client = [System.Net.Sockets.TcpClient]::new()
    try { $client.Connect('127.0.0.1', $Number); return $true } catch { return $false } finally { $client.Dispose() }
}
function Wait-LocalPort([int]$Number) {
    for ($i = 0; $i -lt 50; $i++) {
        if (Test-LocalPort $Number) { return }
        Start-Sleep -Milliseconds 200
    }
    throw "Layanan pada port $Number tidak siap. Periksa log dalam .runtime."
}
$mysqlData = Join-Path $runtime 'mysql'
if (-not (Test-Path -LiteralPath (Join-Path $mysqlData 'mysql'))) {
    New-Item -ItemType Directory -Force -Path $mysqlData | Out-Null
    & "$MySqlBase\bin\mysqld.exe" --no-defaults --initialize-insecure "--basedir=$MySqlBase" "--datadir=$mysqlData" --console
    if ($LASTEXITCODE -ne 0) { throw 'Inisialisasi MySQL gagal.' }
}
if (-not (Test-LocalPort 3307)) {
    $mysqlArguments = @('--no-defaults', "--basedir=`"$MySqlBase`"", "--datadir=`"$mysqlData`"", '--bind-address=127.0.0.1', '--port=3307', '--mysqlx=0', "--log-error=`"$runtime/mysql-error.log`"")
    $mysqlProcess = Start-Process -FilePath "$MySqlBase\bin\mysqld.exe" -ArgumentList $mysqlArguments -WindowStyle Hidden -PassThru
    $mysqlProcess.Id | Set-Content -LiteralPath (Join-Path $runtime 'mysql-process.id')
    Wait-LocalPort 3307
}
Push-Location $project
try {
    if (-not (Test-Path -LiteralPath '.env')) {
        & "$PhpBase\php.exe" scripts/setup-local.php
        if ($LASTEXITCODE -ne 0) { throw 'Setup konfigurasi gagal.' }
    }
    if (-not (Test-Path -LiteralPath 'vendor/autoload.php')) { throw 'Jalankan composer install sebelum memulai aplikasi.' }
    & "$PhpBase\php.exe" artisan migrate --force --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Migrasi gagal. Periksa .env dan koneksi database.' }
} finally { Pop-Location }

$projectPath = $project.Replace('\', '/')
$apachePath = $ApacheBase.Replace('\', '/')
$phpPath = $PhpBase.Replace('\', '/')
$listen = if ($LoopbackOnly) { "127.0.0.1:$Port" } else { "0.0.0.0:$Port" }
$configuration = @"
ServerRoot "$apachePath"
Listen $listen
ServerName 127.0.0.1
PidFile "$projectPath/.runtime/httpd.pid"
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule log_config_module modules/mod_log_config.so
LoadModule php_module "$phpPath/php8apache2_4.dll"
PHPIniDir "$phpPath"
ServerTokens Prod
ServerSignature Off
TypesConfig "$apachePath/conf/mime.types"
DocumentRoot "$projectPath/public"
DirectoryIndex index.php
ErrorLog "$projectPath/.runtime/apache-error.log"
LogLevel warn
LogFormat "%h %t %m %>s %b" privacy
CustomLog "$projectPath/.runtime/apache-access.log" privacy
<Directory />
    AllowOverride None
    Require all denied
</Directory>
<Directory "$projectPath/public">
    AllowOverride None
    Options -Indexes
    Require all granted
    FallbackResource /index.php
</Directory>
<FilesMatch "\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
php_admin_flag display_errors Off
php_admin_flag log_errors On
php_admin_value error_log "$projectPath/.runtime/php-error.log"
php_admin_value upload_max_filesize 10M
php_admin_value post_max_size 12M
"@
$apacheConfig = Join-Path $runtime 'httpd.conf'
$configuration | Set-Content -LiteralPath $apacheConfig -Encoding Ascii
& "$ApacheBase\bin\httpd.exe" -t -f $apacheConfig
if ($LASTEXITCODE -ne 0) { throw 'Konfigurasi Apache tidak valid.' }
if (Test-LocalPort $Port) { throw "Port $Port sudah digunakan. Hentikan instance sebelumnya atau pilih -Port lain." }
$apacheProcess = Start-Process -FilePath "$ApacheBase\bin\httpd.exe" -ArgumentList @('-f', "`"$apacheConfig`"") -WindowStyle Hidden -PassThru
$apacheProcess.Id | Set-Content -LiteralPath (Join-Path $runtime 'apache-process.id')
Wait-LocalPort $Port
& (Join-Path $PSScriptRoot 'start-scheduler.ps1') -PhpBase $PhpBase
Write-Host "Aplikasi siap: http://127.0.0.1:$Port"
if (-not $LoopbackOnly) { Write-Host "Laptop siswa: http://IP-LOKAL-SERVER:$Port (atur firewall jaringan privat)." }
Write-Host 'URL guru: jalankan php artisan election:teacher-url di terminal server.'
Write-Host 'Database terisolasi proyek ini berjalan pada 127.0.0.1:3307.'
