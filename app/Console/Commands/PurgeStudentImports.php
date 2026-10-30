<?php

namespace App\Console\Commands;

use App\Services\StudentImportPreviewService;
use Illuminate\Console\Command;

class PurgeStudentImports extends Command
{
    protected $signature = 'students:purge-imports';
    protected $description = 'Hapus berkas pratinjau impor siswa yang telah kedaluwarsa';

    public function handle(StudentImportPreviewService $previews): int
    {
        $this->info($previews->purgeExpired().' pratinjau impor kedaluwarsa dihapus.');
        return self::SUCCESS;
    }
}
