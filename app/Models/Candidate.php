<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candidate extends Model
{
    public $timestamps = false;
    protected $fillable = ['number', 'name', 'photo_path', 'vision', 'mission'];

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
