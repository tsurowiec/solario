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

    public float $peakPayable;

    public float $offPeakPayable;

    public float $peakPayableRatio;

    public float $offPeakPayableRatio;

    public float $paidRatio;

    public float $sunRatio;

    public float $peakRatio;

    public float $offPeakRatio;

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

        $this->peakPayable = $peakConsumed - self::FED_IN_RATIO * $peakFedIn;
        $this->offPeakPayable = $this->offPeakConsumed - self::FED_IN_RATIO * $this->offPeakFedIn;
        $clampedPeak = max(0, $this->peakPayable);
        $clampedOffPeak = max(0, $this->offPeakPayable);
        $clampedTotal = $clampedPeak + $clampedOffPeak;
        $this->peakPayableRatio = $clampedTotal > 0 ? $clampedPeak / $clampedTotal : 0.0;
        $this->offPeakPayableRatio = $clampedTotal > 0 ? $clampedOffPeak / $clampedTotal : 0.0;
        $peakAmount = self::PEAK_RATE * $this->peakPayable;
        $offPeakAmount = self::OFF_PEAK_RATE * $this->offPeakPayable;
        $this->amount = $peakAmount + $offPeakAmount;
        $this->pricePerUnit = $this->totalUsage > 0 ? $this->amount / $this->totalUsage : 0.0;
        $this->paidRatio = ($this->peakPayable + $this->offPeakPayable) < 0 ? 0.0 : ($this->peakPayable + $this->offPeakPayable) / $this->totalUsage;
        $this->sunRatio = 1 - $this->paidRatio;
        $this->peakRatio = $this->paidRatio * $this->peakPayableRatio;
        $this->offPeakRatio = $this->paidRatio * $this->offPeakPayableRatio;
    }

    public function toDate(): Carbon
    {
        return Carbon::parse($this->to);
    }
}
