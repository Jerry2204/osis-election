<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StudentImportService
{
    public function import(string $path): int
    {
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'Berkas harus berupa CSV yang dapat dibaca, maksimal 10 MB.']);
        }
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Berkas CSV tidak dapat dibaca. Silakan unggah ulang.']);
        }
        $rows = [];
        $seen = [];
        $errors = [];
        $line = 1;
        $records = 0;
        try {
            $header = fgetcsv($handle, null, ',', '"', '');
            if ($header !== false) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
            }
            if ($header !== ['nis', 'nama', 'kelas']) {
                throw ValidationException::withMessages(['file' => 'Header CSV harus persis: nis,nama,kelas (pemisah koma).']);
            }
            while (($fields = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $line++;
                if ($fields === [null]) {
                    continue;
                }
                if (++$records > 20000) {
                    $errors[] = 'Maksimal 20.000 baris siswa per impor.';
                    break;
                }
                if (count($fields) !== 3 || ! mb_check_encoding(implode('', $fields), 'UTF-8')) {
                    $errors[] = "Baris {$line}: harus memiliki tiga kolom dan menggunakan UTF-8.";
                } else {
                    [$nis, $name, $class] = array_map('trim', $fields);
                    $row = ['nis' => $nis, 'name' => $name, 'class_name' => $class];
                    $validator = Validator::make($row, [
                        'nis' => ['required', 'string', 'max:32', 'regex:/\A[0-9]+\z/'],
                        'name' => ['required', 'string', 'max:120'],
                        'class_name' => ['required', 'string', 'max:60'],
                    ], [], ['nis' => 'NIS', 'name' => 'nama', 'class_name' => 'kelas']);
                    if ($validator->fails()) {
                        $errors[] = "Baris {$line}: ".$validator->errors()->first();
                    } elseif (isset($seen['nis:'.$nis])) {
                        $errors[] = "Baris {$line}: NIS ganda dengan baris ".$seen['nis:'.$nis].'.';
                    } else {
                        $seen['nis:'.$nis] = $line;
                        $rows[] = $row;
                    }
                }
                if (count($errors) >= 30) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }
        if (! $rows && ! $errors) {
            $errors[] = 'Tidak ada siswa dalam berkas.';
        }
        if ($errors) {
            $errors[] = 'Impor dibatalkan. Tidak ada data yang disimpan. Perbaiki kesalahan yang ditampilkan, lalu unggah ulang.';
            throw ValidationException::withMessages(['file' => $errors]);
        }
        DB::transaction(function () use ($rows, $seen) {
            app(ElectionService::class)->requirePreparation();
            foreach (array_chunk($rows, 500) as $chunk) {
                $existing = Student::query()->whereIn('nis', array_column($chunk, 'nis'))->pluck('nis');
                if ($existing->isNotEmpty()) {
                    $line = $seen['nis:'.$existing->first()];
                    throw ValidationException::withMessages(['file' => "Baris {$line}: NIS sudah terdaftar di database. Impor dibatalkan; tidak ada data yang disimpan atau ditimpa."]);
                }
                Student::query()->insert($chunk);
            }
        });
        return count($rows);
    }
}
