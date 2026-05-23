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
    }

    public function toDate(): Carbon
    {
        return Carbon::parse($this->to);
    }
}
