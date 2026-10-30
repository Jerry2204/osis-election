<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Participation extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'student_id';
    protected $fillable = ['student_id'];
}
