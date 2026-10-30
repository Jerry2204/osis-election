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
        DB::transaction(function () use ($context, $token) {
            // All mutations acquire the singleton first, preventing races with closing.
            if (ElectionSetting::locked()->status !== 'open') {
                throw new VotingRejected('Pemilihan sudah ditutup. Suara tidak dikirim.');
            }
            $student = Student::query()->lockForUpdate()->find($context['student_id'] ?? null);
            if (! $student) {
                throw new VotingRejected('Sesi pemilihan tidak valid. Silakan masukkan NIS kembali.');
            }
            if (Participation::query()->whereKey($student->id)->lockForUpdate()->exists()) {
                throw new VotingRejected('NIS ini sudah digunakan untuk memilih.');
            }
            if (($context['expires_at'] ?? 0) <= now()->timestamp ||
                ! hash_equals((string) ($context['token'] ?? ''), $token)) {
                throw new VotingRejected('Sesi pemilihan berakhir atau formulir sudah tidak berlaku. Silakan mulai kembali.');
            }
            $candidate = Candidate::query()->find($context['candidate_id'] ?? null);
            if (! $candidate) {
                throw new VotingRejected('Pilihan calon tidak valid. Silakan mulai kembali.');
            }
            Participation::query()->create(['student_id' => $student->id]);
            Vote::query()->create(['id' => (string) Str::uuid(), 'candidate_id' => $candidate->id]);
        }, attempts: 3);
    }
}
