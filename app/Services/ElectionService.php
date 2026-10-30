<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\ElectionSetting;
use App\Models\Participation;
use App\Models\Student;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ElectionService
{
    public function requirePreparation(): ElectionSetting
    {
        $setting = ElectionSetting::locked();
        if ($setting->status !== 'preparation') {
            throw ValidationException::withMessages(['election' => 'Data calon dan siswa sudah dikunci karena pemilihan telah dibuka.']);
        }
        return $setting;
    }

    public function readiness(): array
    {
        return [
            'Data siswa tersedia' => Student::query()->exists(),
            'Minimal dua calon tersedia' => Candidate::query()->count() >= 2,
            'Seluruh foto calon tersedia' => Candidate::query()->exists() && Candidate::all()->every(
                fn (Candidate $candidate) => Storage::disk('photos')->exists($candidate->photo_path)
            ),
            'Suara dan partisipasi masih kosong' => ! Vote::query()->exists() && ! Participation::query()->exists(),
        ];
    }

    public function open(): void
    {
        DB::transaction(function () {
            $setting = $this->requirePreparation();
            if (in_array(false, $this->readiness(), true)) {
                throw ValidationException::withMessages(['election' => 'Pemilihan belum siap. Lengkapi seluruh pemeriksaan kesiapan.']);
            }
            $setting->update(['status' => 'open']);
        });
    }

    public function close(): void
    {
        DB::transaction(function () {
            $setting = ElectionSetting::locked();
            if ($setting->status !== 'open') {
                throw ValidationException::withMessages(['election' => 'Hanya pemilihan yang sedang dibuka yang dapat ditutup.']);
            }
            $setting->update(['status' => 'closed']);
        });
    }

    public function results(): array
    {
        abort_unless(ElectionSetting::current()->status === 'closed', 403, 'Rekap hanya tersedia setelah pemilihan ditutup.');
        $registered = Student::query()->count();
        $participated = Participation::query()->count();
        $votes = Vote::query()->count();
        return [
            'registered' => $registered,
            'participated' => $participated,
            'remaining' => $registered - $participated,
            'votes' => $votes,
            'consistent' => $participated === $votes,
            'candidates' => Candidate::query()->withCount('votes')->orderBy('number')->get(),
        ];
    }
}
