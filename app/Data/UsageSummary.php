<?php

namespace App\Data;

use Carbon\Carbon;

readonly class UsageSummary
{
    private const PEAK_RATE = 1.40;

    private const OFF_PEAK_RATE = 0.70;

    private const FED_IN_RATIO = 0.80;

    public int $autoConsumed;

    public float $autoConsumedRatio;

    public int $totalUsage;

    public int $consumed;

    public int $fedIn;

    public float $amount;

    public float $pricePerUnit;

    public function __construct(
        public string $from,
        public string $to,
        public int $pvGenerated,
        public int $peakConsumed,
        public int $offPeakConsumed,
        public int $peakFedIn,
        public int $offPeakFedIn,
    ) {
        $this->autoConsumed = $pvGenerated - $peakFedIn - $offPeakFedIn;
        $this->autoConsumedRatio = $pvGenerated > 0 ? $this->autoConsumed / $pvGenerated * 100 : 0;
        $this->consumed = $peakConsumed + $offPeakConsumed;
        $this->fedIn = $peakFedIn + $offPeakFedIn;
        $this->totalUsage = $this->consumed + $this->autoConsumed;

        $peak = self::PEAK_RATE * ($peakConsumed - self::FED_IN_RATIO * $peakFedIn);
        $offPeak = self::OFF_PEAK_RATE * ($offPeakConsumed - self::FED_IN_RATIO * $offPeakFedIn);
        $this->amount = $peak + $offPeak;
        $this->pricePerUnit = $this->totalUsage > 0 ? $this->amount / $this->totalUsage : 0.0;
    }

    public function toDate(): Carbon
    {
        return Carbon::parse($this->to);
    }
}
