<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    public $timestamps = false;
    protected $fillable = ['nis', 'name', 'class_name'];

    public function participation(): HasOne
    {
        return $this->hasOne(Participation::class);
    }
}
