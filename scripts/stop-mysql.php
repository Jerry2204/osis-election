<?php

$root = dirname(__DIR__);
$credentials = $root.'/.runtime/mysql-admin.json';
if (! is_file($credentials)) {
    fwrite(STDERR, "Tidak ada konfigurasi MySQL lokal milik proyek ini.\n");
    exit(1);
}
$admin = json_decode(file_get_contents($credentials), true, flags: JSON_THROW_ON_ERROR);
$pdo = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', 'root', $admin['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
$actual = realpath($pdo->query('SELECT @@datadir')->fetchColumn());
$expected = realpath($root.'/.runtime/mysql');
if (! $actual || ! $expected || strcasecmp($actual, $expected) !== 0) {
    throw new RuntimeException('Menolak menghentikan instance MySQL lain.');
}
$pdo->exec('SHUTDOWN');
echo "MySQL lokal dihentikan dengan bersih.\n";
