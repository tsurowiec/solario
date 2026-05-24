<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarCharge extends Model
{
    public const CARS = ['tesia', 'tessy'];

    protected $fillable = [
        'date',
        'car_id',
        'charged',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
