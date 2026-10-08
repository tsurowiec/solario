<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarCharge extends Model
{
    public const CARS = ['tesia', 'tessy'];

    /** Icon color per car (Tailwind text class). */
    public const COLORS = ['tesia' => 'text-zinc-400', 'tessy' => 'text-red-400'];

    protected $fillable = [
        'date',
        'car_id',
        'charged',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
