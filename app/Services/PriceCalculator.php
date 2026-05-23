<?php

namespace App\Services;

use App\Data\UsageSummary;

class PriceCalculator
{
    private const PEAK_RATE = 1.40;

    private const OFF_PEAK_RATE = 0.70;

    private const FED_IN_RATIO = 0.80;

    public function calculate(UsageSummary $usage): float
    {
        $peak = self::PEAK_RATE * ($usage->peakConsumed - self::FED_IN_RATIO * $usage->peakFedIn);
        $offPeak = self::OFF_PEAK_RATE * ($usage->offPeakConsumed - self::FED_IN_RATIO * $usage->offPeakFedIn);

        return $peak + $offPeak;
    }
}
