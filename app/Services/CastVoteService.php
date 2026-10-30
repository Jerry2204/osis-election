<?php

namespace App\Services;

use App\Exceptions\VotingRejected;
use App\Models\Candidate;
use App\Models\ElectionSetting;
use App\Models\Participation;
use App\Models\Student;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CastVoteService
{
    public function cast(array $context, string $token): void
    {
        $rejection = DB::transaction(function () use ($context, $token) {
            // All mutations acquire the singleton first, preventing races with closing.
            $setting = app(ElectionService::class)->reconcileLocked(ElectionSetting::locked());
            if ($setting->status !== 'open') {
                return new VotingRejected('Pemilihan belum dibuka atau sudah ditutup. Suara tidak dikirim.');
            }
            $student = Student::query()->lockForUpdate()->find($context['student_id'] ?? null);
            if (! $student) {
                return new VotingRejected('Sesi pemilihan tidak valid. Silakan masukkan NIS kembali.');
            }
            if (Participation::query()->whereKey($student->id)->lockForUpdate()->exists()) {
                return new VotingRejected('NIS ini sudah digunakan untuk memilih.');
            }
            if (($context['expires_at'] ?? 0) <= now()->timestamp ||
                ! hash_equals((string) ($context['token'] ?? ''), $token)) {
                return new VotingRejected('Sesi pemilihan berakhir atau formulir sudah tidak berlaku. Silakan mulai kembali.');
            }
            $candidate = Candidate::query()->find($context['candidate_id'] ?? null);
            if (! $candidate) {
                return new VotingRejected('Pilihan calon tidak valid. Silakan mulai kembali.');
            }
            Participation::query()->create(['student_id' => $student->id]);
            Vote::query()->create(['id' => (string) Str::uuid(), 'candidate_id' => $candidate->id]);
            return null;
        }, attempts: 3);
        // Keep an automatic opening/closure committed even when this vote is
        // rejected. Throwing inside the transaction would undo that transition.
        if ($rejection) {
            throw $rejection;
        }
    }
}
