<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class BackupElection extends Command
{
    protected $signature = 'election:backup';
    protected $description = 'Buat dump MySQL konsisten di luar public, dengan nama unik dan tanpa menaruh password di argumen';

    public function handle(): int
    {
        $connection = config('database.connections.'.config('database.default'));
        if (($connection['driver'] ?? null) !== 'mysql' || ! preg_match('/\A[a-zA-Z0-9_]+\z/', $connection['database'] ?? '')) {
            $this->error('Backup ini memerlukan koneksi MySQL dan nama database berupa huruf, angka, atau garis bawah.');
            return self::FAILURE;
        }
        $directory = config('election.backup_directory');
        File::ensureDirectoryExists($directory, 0700);
        $resolved = str_replace('\\', '/', realpath($directory));
        $public = str_replace('\\', '/', realpath(public_path()));
        if (strtolower($resolved) === strtolower($public) || str_starts_with(strtolower($resolved).'/', strtolower($public).'/')) {
            $this->error('Folder backup tidak boleh berada dalam public/.');
            return self::FAILURE;
        }
        $filename = 'osis-'.now()->format('Y-m-d-His').'-'.bin2hex(random_bytes(4)).'.sql';
        $temporary = $resolved.'/'.$filename.'.partial';
        try {
            $process = new Process([
                config('election.mysqldump'),
                '--protocol=TCP', '--host='.$connection['host'], '--port='.$connection['port'],
                '--user='.$connection['username'], '--single-transaction', '--quick',
                '--skip-lock-tables', '--skip-add-locks', '--no-tablespaces', '--set-gtid-purged=OFF', '--column-statistics=0',
                '--default-character-set=utf8mb4', '--result-file='.$temporary, $connection['database'],
            ], base_path(), ['MYSQL_PWD' => $connection['password']], null, 300);
            $process->run();
            if (! $process->isSuccessful() || ! is_file($temporary) || filesize($temporary) === 0) {
                throw new \RuntimeException('Dump gagal');
            }
            if (! rename($temporary, $resolved.'/'.$filename)) {
                throw new \RuntimeException('Tidak dapat menyelesaikan berkas');
            }
            $this->info('Backup tersimpan: '.$resolved.'/'.$filename);
            return self::SUCCESS;
        } catch (Throwable) {
            if (is_file($temporary)) {
                unlink($temporary);
            }
            $this->error('Backup gagal. Periksa layanan MySQL, MYSQLDUMP_PATH, izin database, dan ruang media.');
            return self::FAILURE;
        }
    }
}
