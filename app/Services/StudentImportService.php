<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StudentImportService
{
    public const MAX_ROWS = 20000;
    public const MAX_BYTES = 10 * 1024 * 1024;

    /** Validate the whole CSV without saving students. Rows are returned only for an eventual atomic import. */
    public function inspect(string $path): array
    {
        if (! is_file($path) || ! is_readable($path) || filesize($path) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'Berkas harus berupa CSV yang dapat dibaca, maksimal 10 MB.']);
        }
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Berkas CSV tidak dapat dibaca. Silakan unggah ulang.']);
        }
        $rows = [];
        $seen = [];
        $sample = [];
        $errors = [];
        $sampleErrors = [];
        $sampleLines = [];
        $errorCount = 0;
        $records = 0;
        $line = 1;
        $addError = function (string $message, ?int $rowLine = null) use (&$errors, &$errorCount, &$sampleErrors, &$sampleLines): void {
            $errorCount++;
            if (count($errors) < 30) {
                $errors[] = $message;
            }
            if ($rowLine !== null && isset($sampleLines[$rowLine])) {
                $sampleErrors[$rowLine] = $message;
            }
        };
        try {
            $header = fgetcsv($handle, null, ',', '"', '');
            if ($header !== false) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
            }
            $headerValid = $header === ['nis', 'nama', 'kelas'];
            if (! $headerValid) {
                $addError('Header CSV harus persis: nis,nama,kelas (pemisah koma).');
            }
            while (($fields = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $line++;
                if ($fields === [null]) {
                    continue;
                }
                $records++;
                $encodingValid = mb_check_encoding(implode('', $fields), 'UTF-8');
                $normalized = array_map(fn ($value) => trim(mb_convert_encoding((string) $value, 'UTF-8', 'UTF-8')), $fields);
                if (count($sample) < 20) {
                    $sampleLines[$line] = true;
                    $sample[] = [
                        'line' => $line,
                        'nis' => mb_strimwidth($normalized[0] ?? '', 0, 160, '…'),
                        'name' => mb_strimwidth($normalized[1] ?? '', 0, 160, '…'),
                        'class_name' => mb_strimwidth($normalized[2] ?? '', 0, 160, '…'),
                    ];
                }
                if (! $headerValid) {
                    continue;
                }
                if ($records > self::MAX_ROWS) {
                    if ($records === self::MAX_ROWS + 1) {
                        $addError('Maksimal 20.000 baris siswa per impor.');
                    }
                    continue;
                }
                if (count($fields) !== 3 || ! $encodingValid) {
                    $addError("Baris {$line}: harus memiliki tiga kolom dan menggunakan UTF-8.", $line);
                    continue;
                }
                [$nis, $name, $class] = $normalized;
                $row = ['nis' => $nis, 'name' => $name, 'class_name' => $class];
                $validator = Validator::make($row, [
                    'nis' => ['required', 'string', 'max:32', 'regex:/\A[0-9]+\z/'],
                    'name' => ['required', 'string', 'max:120'],
                    'class_name' => ['required', 'string', 'max:60'],
                ], [], ['nis' => 'NIS', 'name' => 'nama', 'class_name' => 'kelas']);
                if ($validator->fails()) {
                    $addError("Baris {$line}: ".$validator->errors()->first(), $line);
                } elseif (isset($seen['nis:'.$nis])) {
                    $addError("Baris {$line}: NIS ganda dengan baris ".$seen['nis:'.$nis].'.', $line);
                } else {
                    $seen['nis:'.$nis] = $line;
                    $rows[] = $row;
                }
            }
        } finally {
            fclose($handle);
        }
        if ($headerValid && $records === 0) {
            $addError('Tidak ada siswa dalam berkas.');
        }
        $existingCount = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            foreach (Student::query()->whereIn('nis', array_column($chunk, 'nis'))->pluck('nis') as $nis) {
                $rowLine = $seen['nis:'.$nis];
                $existingCount++;
                $addError("Baris {$rowLine}: NIS sudah terdaftar di database. Tidak ada data yang akan ditimpa.", $rowLine);
            }
        }
        foreach ($sample as &$sampleRow) {
            $sampleRow['error'] = $headerValid ? ($sampleErrors[$sampleRow['line']] ?? null) : 'Header CSV tidak sesuai.';
        }
        unset($sampleRow);
        $validCount = count($rows) - $existingCount;
        return [
            'records' => $records,
            'valid_count' => $validCount,
            'invalid_count' => $records - $validCount,
            'sample' => $sample,
            'errors' => $errors,
            'error_count' => $errorCount,
            'can_import' => $errorCount === 0,
            'rows' => $rows,
            'row_lines' => $seen,
        ];
    }

    public function import(string $path): int
    {
        app(ElectionService::class)->reconcile();
        $inspection = $this->inspect($path);
        if (! $inspection['can_import']) {
            throw ValidationException::withMessages(['file' => array_merge($inspection['errors'], [
                'Impor dibatalkan. Tidak ada data yang disimpan. Perbaiki kesalahan yang ditampilkan, lalu unggah ulang.',
            ])]);
        }
        $rows = $inspection['rows'];
        $seen = $inspection['row_lines'];
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
