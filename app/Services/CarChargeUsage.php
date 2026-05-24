<?php

namespace App\Services;

use App\Models\CarCharge;
use Carbon\Carbon;

class CarChargeUsage
{
    public function year(string $carId, Carbon $date): int
    {
        return $this->between($carId, $date->copy()->startOfYear(), $date->copy()->endOfYear());
    }

    public function month(string $carId, Carbon $date): int
    {
        return $this->between($carId, $date->copy()->startOfMonth(), $date->copy()->endOfMonth());
    }

    public function between(string $carId, Carbon $from, Carbon $to): int
    {
        return (int) CarCharge::query()
            ->where('car_id', $carId)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->sum('charged');
    }
}
