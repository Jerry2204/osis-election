<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ElectionSetting extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $fillable = [
        'status', 'school_name', 'election_title', 'osis_period', 'logo_path',
        'opens_at', 'closes_at', 'opened_at', 'closed_at',
        'report_place', 'committee_chair', 'osis_advisor', 'principal_name',
        'results_published',
    ];

    protected function casts(): array
    {
        return [
            'results_published' => 'boolean',
            'opens_at' => 'immutable_datetime',
            'closes_at' => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    // SQL DATETIME has no timezone. These four fields always use UTC, even
    // though the rest of this local application displays Asia/Jakarta time.
    protected function asDateTime($value)
    {
        return $value instanceof DateTimeInterface
            ? Carbon::instance($value)->utc()
            : Carbon::parse($value, 'UTC')->utc();
    }

    public function schoolLabel(): string
    {
        return trim((string) $this->school_name) ?: 'Suara OSIS';
    }

    public function electionLabel(): string
    {
        return trim((string) $this->election_title) ?: 'Pemilihan Ketua OSIS';
    }

    public static function current(): self
    {
        return static::query()->findOrFail(1);
    }

    public static function locked(): self
    {
        return static::query()->lockForUpdate()->findOrFail(1);
    }
}
