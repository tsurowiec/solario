<?php

namespace App\Services;

use App\Models\CarCharge;
use App\Models\Season;
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

    public function season(string $carId, Season $season): int
    {
        return $this->between($carId, Carbon::parse($season->starting_date), Carbon::parse($season->endDate()));
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
