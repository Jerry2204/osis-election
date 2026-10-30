<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PurgeSessions extends Command
{
    protected $signature = 'election:purge-sessions';
    protected $description = 'Hapus berkas sesi yang melewati batas sesi terpanjang guru atau siswa';

    public function handle(): int
    {
        if (config('session.driver') !== 'file') {
            return self::SUCCESS;
        }
        // Teacher CSV previews need a 30-minute session. Voter eligibility is
        // still independently limited by voting.expires_at to five minutes.
        $lifetime = max(30, (int) config('election.base_session_lifetime_minutes', 5), (int) config('election.teacher_session_lifetime_minutes', 30));
        $cutoff = time() - $lifetime * 60;
        foreach (File::files(config('session.files')) as $file) {
            if (preg_match('/\A[a-zA-Z0-9]{40}\z/', $file->getFilename()) && $file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
            }
        }
        return self::SUCCESS;
    }
}
