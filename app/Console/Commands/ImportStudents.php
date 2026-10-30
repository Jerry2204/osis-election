<?php

namespace App\Console\Commands;

use App\Services\StudentImportService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportStudents extends Command
{
    protected $signature = 'students:import {file : CSV UTF-8 dengan header nis,nama,kelas}';
    protected $description = 'Impor siswa secara utuh pada tahap persiapan; tidak menimpa NIS yang ada';

    public function handle(StudentImportService $service): int
    {
        try {
            $count = $service->import($this->argument('file'));
        } catch (ValidationException $exception) {
            foreach (collect($exception->errors())->flatten() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }
        $this->info($count.' siswa berhasil diimpor. Angka nol di awal NIS dipertahankan.');
        return self::SUCCESS;
    }
}
