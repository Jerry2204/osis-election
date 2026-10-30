<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectionSetting extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $fillable = ['status'];

    public static function current(): self
    {
        return static::query()->findOrFail(1);
    }

    public static function locked(): self
    {
        return static::query()->lockForUpdate()->findOrFail(1);
    }
}
