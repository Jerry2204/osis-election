<?php

// Optional convenience setup for the isolated MySQL instance started by start-local.ps1.
// This refuses to touch another MySQL data directory or overwrite existing .env files.
$root = dirname(__DIR__);
$adminPath = $root.'/.runtime/mysql-admin.json';
if (is_file($root.'/.env') || is_file($root.'/.env.testing')) {
    fwrite(STDERR, "Konfigurasi sudah ada. Setup tidak menimpa .env atau .env.testing.\n");
    exit(1);
}
$admin = is_file($adminPath) ? json_decode(file_get_contents($adminPath), true, flags: JSON_THROW_ON_ERROR) : ['password' => ''];
$pdo = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', 'root', $admin['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$actual = realpath($pdo->query('SELECT @@datadir')->fetchColumn());
$expected = realpath($root.'/.runtime/mysql');
if (! $actual || ! $expected || strcasecmp($actual, $expected) !== 0) {
    throw new RuntimeException('Setup hanya diizinkan untuk data MySQL dalam .runtime/mysql proyek ini.');
}
$appPassword = bin2hex(random_bytes(24));
$testPassword = bin2hex(random_bytes(24));
foreach (['osis_election' => ['osis_app', $appPassword], 'osis_election_testing' => ['osis_test', $testPassword]] as $database => [$username, $password]) {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin");
    $pdo->exec("CREATE USER IF NOT EXISTS '$username'@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($password));
    $pdo->exec("ALTER USER '$username'@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($password));
    $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON `$database`.* TO '$username'@'127.0.0.1'");
}
$example = file_get_contents($root.'/.env.example');
$key = 'base64:'.base64_encode(random_bytes(32));
$teacherKey = bin2hex(random_bytes(32));
$environment = str_replace([
    'APP_KEY=', 'APP_URL=http://192.168.1.10:8080', 'DB_PORT=3306', 'DB_PASSWORD=', 'TEACHER_KEY=',
], [
    'APP_KEY='.$key, 'APP_URL=http://127.0.0.1:8080', 'DB_PORT=3307', 'DB_PASSWORD='.$appPassword, 'TEACHER_KEY='.$teacherKey,
], $example);
file_put_contents($root.'/.env', $environment, LOCK_EX);
$testing = str_replace([
    'APP_ENV=production', 'DB_DATABASE=osis_election', 'DB_USERNAME=osis_app', 'DB_PASSWORD='.$appPassword,
], [
    'APP_ENV=testing', 'DB_DATABASE=osis_election_testing', 'DB_USERNAME=osis_test', 'DB_PASSWORD='.$testPassword,
], $environment);
file_put_contents($root.'/.env.testing', $testing, LOCK_EX);
if ($admin['password'] === '') {
    $admin['password'] = bin2hex(random_bytes(32));
    file_put_contents($adminPath, json_encode($admin, JSON_THROW_ON_ERROR), LOCK_EX);
    $pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY ".$pdo->quote($admin['password']));
}
echo "Database aplikasi dan pengujian terpisah siap. Konfigurasi lokal tersimpan tanpa menampilkan kredensial.\n";
