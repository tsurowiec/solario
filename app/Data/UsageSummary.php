<?php

namespace App\Data;

use Carbon\Carbon;

readonly class UsageSummary
{
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
        private float $peakRate = 1.40,
        private float $offPeakRate = 0.70,
        private float $fedInRatio = 0.80,
    ) {
        $this->autoConsumed = $pvGenerated - $peakFedIn - $offPeakFedIn;
        $this->autoConsumedRatio = $pvGenerated > 0 ? $this->autoConsumed / $pvGenerated * 100 : 0;
        $this->consumed = $peakConsumed + $offPeakConsumed;
        $this->fedIn = $peakFedIn + $offPeakFedIn;
        $this->totalUsage = $this->consumed + $this->autoConsumed;

        $this->peakPayable = $peakConsumed - $this->fedInRatio * $peakFedIn;
        $this->offPeakPayable = $this->offPeakConsumed - $this->fedInRatio * $this->offPeakFedIn;
        $clampedPeak = max(0, $this->peakPayable);
        $clampedOffPeak = max(0, $this->offPeakPayable);
        $clampedTotal = $clampedPeak + $clampedOffPeak;
        $this->peakPayableRatio = $clampedTotal > 0 ? $clampedPeak / $clampedTotal : 0.0;
        $this->offPeakPayableRatio = $clampedTotal > 0 ? $clampedOffPeak / $clampedTotal : 0.0;
        $peakAmount = $this->peakRate * $this->peakPayable;
        $offPeakAmount = $this->offPeakRate * $this->offPeakPayable;
        $this->amount = $peakAmount + $offPeakAmount;
        $this->pricePerUnit = $this->totalUsage > 0 ? $this->amount / $this->totalUsage : 0.0;
        $payable = $this->peakPayable + $this->offPeakPayable;
        $this->paidRatio = ($this->totalUsage <= 0 || $payable < 0) ? 0.0 : $payable / $this->totalUsage;
        $this->sunRatio = $this->totalUsage > 0 ? 1 - $this->paidRatio : 0.0;
        $this->peakRatio = $this->paidRatio * $this->peakPayableRatio;
        $this->offPeakRatio = $this->paidRatio * $this->offPeakPayableRatio;
    }

    public function toDate(): Carbon
    {
        return Carbon::parse($this->to);
    }
}
