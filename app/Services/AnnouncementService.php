<?php

namespace App\Services;

use App\Models\ElectionSetting;
use App\Models\Participation;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnouncementService
{
    public function available(ElectionSetting $setting): bool
    {
        return $setting->status === 'closed' && $setting->results_published
            && Participation::query()->count() === Vote::query()->count();
    }

    public function data(ElectionSetting $setting): array
    {
        $results = app(ElectionService::class)->results();
        // Stable order for ties, without declaring an elected winner.
        $candidates = $results['candidates']->sort(fn ($a, $b) =>
            ($b->votes_count <=> $a->votes_count) ?: ($a->number <=> $b->number)
        )->values();
        $highest = (int) ($candidates->max('votes_count') ?? 0);
        $leaders = $highest > 0 ? $candidates->where('votes_count', $highest)->values() : collect();

        return $results + [
            'setting' => $setting,
            'rankedCandidates' => $candidates,
            'leaders' => $leaders,
            'highest' => $highest,
            'participationPercentage' => $results['registered'] ? round($results['participated'] / $results['registered'] * 100, 1) : 0,
        ];
    }

    public function publish(bool $enabled): void
    {
        app(ElectionService::class)->reconcile();
        DB::transaction(function () use ($enabled) {
            $setting = ElectionSetting::locked();
            if ($enabled && $setting->status !== 'closed') {
                throw ValidationException::withMessages(['election' => 'Pengumuman hanya dapat diaktifkan setelah pemilihan ditutup.']);
            }
            if ($enabled && Participation::query()->count() !== Vote::query()->count()) {
                throw ValidationException::withMessages(['election' => 'Jumlah partisipasi dan suara belum sesuai. Periksa data sebelum mengaktifkan pengumuman.']);
            }
            $setting->update(['results_published' => $enabled]);
        });
    }
}
