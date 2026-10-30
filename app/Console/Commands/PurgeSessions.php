<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PurgeSessions extends Command
{
    protected $signature = 'election:purge-sessions';
    protected $description = 'Hapus berkas sesi yang tidak aktif lebih dari lima menit';

    public function handle(): int
    {
        if (config('session.driver') !== 'file') {
            return self::SUCCESS;
        }
        foreach (File::files(config('session.files')) as $file) {
            if (preg_match('/\A[a-zA-Z0-9]{40}\z/', $file->getFilename()) && $file->getMTime() < time() - 300) {
                File::delete($file->getPathname());
            }
        }
        return self::SUCCESS;
    }
}
