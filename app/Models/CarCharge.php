<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarCharge extends Model
{
    public const CARS = ['tesia', 'tessy'];

    /** Icon color per car (Tailwind text class). */
    public const COLORS = ['tesia' => 'text-zinc-400', 'tessy' => 'text-red-400'];

    /** kWh taken from the grid per kWh charged as reported by the car (charging losses). */
    public const GRID_RATIO = 1.1;

    /** Consumption per car (kWh per 100 km). */
    public const CONSUMPTION = ['tesia' => 20, 'tessy' => 15];

    protected $fillable = [
        'date',
        'car_id',
        'charged',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /** kWh taken from the grid for this charge. */
    public function getTakenAttribute(): float
    {
        return $this->charged * self::GRID_RATIO;
    }

    /** km of range added by this charge (charged kWh at the car's consumption). */
    public function getRangeAttribute(): float
    {
        return $this->charged / self::CONSUMPTION[$this->car_id] * 100;
    }
}
