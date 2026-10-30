<?php

namespace App\Services;

use App\Models\Student;

class ParticipationService
{
    public function summary(): array
    {
        // A single aggregate query gives a consistent snapshot without reading votes.
        $classes = Student::query()
            ->leftJoin('participations', 'students.id', '=', 'participations.student_id')
            ->selectRaw('students.class_name, COUNT(*) AS registered, COUNT(participations.student_id) AS participated')
            ->groupBy('students.class_name')->orderBy('students.class_name')->get()
            ->map(fn ($row) => $this->counts((int) $row->registered, (int) $row->participated) + ['class_name' => $row->class_name])
            ->all();

        return $this->counts(array_sum(array_column($classes, 'registered')), array_sum(array_column($classes, 'participated')))
            + ['classes' => $classes];
    }

    private function counts(int $registered, int $participated): array
    {
        return [
            'registered' => $registered,
            'participated' => $participated,
            'remaining' => $registered - $participated,
            'percentage' => $registered ? round($participated / $registered * 100, 1) : 0.0,
        ];
    }
}
