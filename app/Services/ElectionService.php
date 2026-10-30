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
        // A preparation request may have waited for the singleton lock while
        // the opening time passed. Block that race without changing status in
        // a transaction which its caller will roll back on this exception.
        $now = now()->utc();
        if (($setting->closes_at && $now->greaterThanOrEqualTo($setting->closes_at)) ||
            ($setting->opens_at && $now->greaterThanOrEqualTo($setting->opens_at) && ! in_array(false, $this->readiness(), true))) {
            throw ValidationException::withMessages(['election' => 'Jadwal pemilihan sudah tiba. Muat ulang halaman untuk memperbarui status sebelum melanjutkan.']);
        }
        return $setting;
    }

    public function reconcile(): ElectionSetting
    {
        $setting = ElectionSetting::current();
        if ($setting->status === 'closed' || (! $setting->opens_at && ! $setting->closes_at)) {
            return $setting;
        }
        return DB::transaction(fn () => $this->reconcileLocked(ElectionSetting::locked()), attempts: 3);
    }

    public function reconcileLocked(ElectionSetting $setting): ElectionSetting
    {
        if ($setting->status === 'closed') {
            return $setting;
        }
        $now = now()->utc();
        // Expiry takes priority, including when the server missed the entire
        // window or readiness was never completed. Never briefly open late.
        if ($setting->closes_at && $now->greaterThanOrEqualTo($setting->closes_at)) {
            $setting->update(['status' => 'closed', 'closed_at' => $now]);
        } elseif ($setting->status === 'preparation' && $setting->opens_at &&
            $now->greaterThanOrEqualTo($setting->opens_at) && ! in_array(false, $this->readiness(), true)) {
            $setting->update(['status' => 'open', 'opened_at' => $now]);
        }
        return $setting;
    }

    public function scheduleNotice(?ElectionSetting $setting = null): ?string
    {
        $setting ??= ElectionSetting::current();
        if ($setting->status === 'preparation' && $setting->opens_at && now()->utc()->greaterThanOrEqualTo($setting->opens_at)) {
            return 'Jadwal pembukaan sudah tiba, tetapi pemeriksaan kesiapan belum lengkap. Lengkapi data agar pemilihan dapat dibuka otomatis.';
        }
        if ($setting->status === 'closed' && $setting->closes_at && ! $setting->opened_at) {
            return 'Jadwal pemilihan telah berakhir sebelum pemilihan sempat dibuka.';
        }
        return null;
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
        $this->reconcile();
        DB::transaction(function () {
            $setting = $this->requirePreparation();
            if (in_array(false, $this->readiness(), true)) {
                throw ValidationException::withMessages(['election' => 'Pemilihan belum siap. Lengkapi seluruh pemeriksaan kesiapan.']);
            }
            $setting->update(['status' => 'open', 'opened_at' => now()->utc()]);
        });
    }

    public function close(): void
    {
        $this->reconcile();
        DB::transaction(function () {
            $setting = ElectionSetting::locked();
            if ($setting->status !== 'open') {
                throw ValidationException::withMessages(['election' => 'Hanya pemilihan yang sedang dibuka yang dapat ditutup.']);
            }
            $setting->update(['status' => 'closed', 'closed_at' => now()->utc()]);
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
