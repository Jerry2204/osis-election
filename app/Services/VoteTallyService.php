<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\ElectionSetting;

class VoteTallyService
{
    public function snapshot(): array
    {
        // One SQL statement reads all candidate totals from the same snapshot.
        // Derive the denominator from these counts so a concurrent vote cannot
        // make the displayed percentages disagree with the displayed totals.
        $candidates = Candidate::query()->select(['id', 'number', 'name'])->withCount('votes')->orderBy('number')->get();
        $total = (int) $candidates->sum('votes_count');

        return [
            'status' => ElectionSetting::current()->status,
            'total_votes' => $total,
            'candidates' => $candidates->map(fn (Candidate $candidate) => [
                'id' => $candidate->id,
                'number' => $candidate->number,
                'name' => $candidate->name,
                'votes' => (int) $candidate->votes_count,
                'percentage' => $total ? round($candidate->votes_count / $total * 100, 1) : 0,
            ])->all(),
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
