<?php

namespace App\Console\Commands;

use App\Models\ElectionSetting;
use App\Models\Participation;
use App\Models\Student;
use App\Models\Vote;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckElection extends Command
{
    protected $signature = 'election:check {nis? : Periksa status partisipasi satu NIS, tanpa pilihan}';
    protected $description = 'Periksa integritas data atau status siswa setelah gangguan';

    public function handle(): int
    {
        return DB::transaction(function () {
            $setting = ElectionSetting::locked();
            if (($nis = $this->argument('nis')) !== null) {
                $student = Student::query()->where('nis', (string) $nis)->first();
                if (! $student) {
                    $this->error('NIS tidak terdaftar.');
                    return self::FAILURE;
                }
                $this->info($student->participation()->exists() ? 'Sudah memilih.' : 'Belum memilih.');
                return self::SUCCESS;
            }
            $participations = Participation::query()->count();
            $votes = Vote::query()->count();
            $this->table(['Status', 'Terdaftar', 'Partisipasi', 'Suara', 'Konsistensi'], [[
                $setting->status, Student::query()->count(), $participations, $votes, $participations === $votes ? 'Sesuai' : 'BERMASALAH',
            ]]);
            return $participations === $votes ? self::SUCCESS : self::FAILURE;
        });
    }
}
