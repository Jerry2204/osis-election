<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TeacherUrl extends Command
{
    protected $signature = 'election:teacher-url {--generate : Buat kode sekali jika belum tersedia}';
    protected $description = 'Tampilkan URL guru pada terminal server; tidak mengganti kode yang sudah ada';

    public function handle(): int
    {
        $key = (string) config('election.teacher_key');
        if (! preg_match('/\A[a-f0-9]{64}\z/', $key)) {
            if (! $this->option('generate')) {
                $this->error('Kode belum tersedia. Jalankan dengan --generate.');
                return self::FAILURE;
            }
            if (app()->configurationIsCached()) {
                $this->error('Jalankan php artisan config:clear sebelum membuat kode.');
                return self::FAILURE;
            }
            $path = app()->environmentFilePath();
            if (! is_file($path)) {
                $this->error('Salin .env.example menjadi .env terlebih dahulu.');
                return self::FAILURE;
            }
            $key = bin2hex(random_bytes(32));
            $contents = file_get_contents($path);
            $contents = preg_match('/^TEACHER_KEY=.*$/m', $contents)
                ? preg_replace('/^TEACHER_KEY=.*$/m', 'TEACHER_KEY='.$key, $contents)
                : rtrim($contents).PHP_EOL.'TEACHER_KEY='.$key.PHP_EOL;
            if (file_put_contents($path, $contents, LOCK_EX) === false) {
                $this->error('Tidak dapat menyimpan kode ke .env.');
                return self::FAILURE;
            }
        }
        $this->info(rtrim(config('app.url'), '/').'/ruang-guru/'.$key);
        $this->comment('Bagikan hanya kepada guru. Siapa pun yang mengetahui URL ini dapat mengelola pemilihan.');
        return self::SUCCESS;
    }
}
