<?php

namespace App\Data;

use App\Models\Price;
use Carbon\Carbon;

readonly class EnergySummary
{
    public float $autoConsumed;

    public float $autoConsumedRatio;

    public float $totalUsage;

    /** kWh taken from the grid (raw, before balancing), both zones. */
    public float $consumed;

    /** kWh to pay for in the peak zone (T1): balanced consumed − 0.8 × balanced fed-in, never below 0. */
    public float $peakPayable;

    /** Peak credit left over when 0.8 × balanced fed-in exceeds balanced consumed (≥ 0); it is moved to off-peak. */
    public float $peakSurplus;

    /** kWh to pay for in the off-peak zone (T2): balanced consumed − 0.8 × balanced fed-in − peak surplus, never below 0. */
    public float $offPeakPayable;

    /** Credit left over after off-peak (≥ 0). */
    public float $offPeakSurplus;

    /** Share of the paid kWh in the peak zone, 0–100 %. */
    public float $peakPayablePercent;

    /** Share of the paid kWh in the off-peak zone, 0–100 %; 100 % when nothing is paid. */
    public float $offPeakPayablePercent;

    /** Number of days in the period, both ends included. */
    public int $days;

    /**
     * Gross cost in PLN incl. VAT; null without prices:
     * (monthly fees × days ÷ days in month + Σ zone: (sell + distr + quality) × payable + (oze + cogen) × raw consumed) × VAT.
     * Monthly fees are prorated to the days the period covers.
     */
    public ?float $amount;

    /** Amount projected to the whole month (amount ÷ days × days in month); null without prices or when the period covers the full month. */
    public ?float $estimatedAmount;

    /** Amount ÷ total usage (PLN/kWh); null without prices. */
    public ?float $pricePerUnit;

    /** @var array<string, float|null> PLN per car (kWh charged × price per kWh); null without prices. */
    public array $carAmounts;

    /** kWh used by the household: total usage − all car charges. */
    public float $householdUsage;

    /** PLN for the household: amount − all car amounts; null without prices. */
    public ?float $householdAmount;

    /**
     * @param  string  $from  first day included (Y-m-d)
     * @param  string  $to  last day included (Y-m-d)
     * @param  float  $pvGenerated  kWh produced by the PV inverter
     * @param  float  $peakConsumed  kWh taken from the grid in T1 (raw, before balancing)
     * @param  float  $offPeakConsumed  kWh taken from the grid in T2 (raw, before balancing)
     * @param  float  $fedIn  kWh sent to the grid (raw, before balancing)
     * @param  float  $peakBalancedConsumed  kWh taken from the grid in T1 after hourly balancing
     * @param  float  $peakBalancedFedIn  kWh sent to the grid in T1 after hourly balancing
     * @param  float  $offPeakBalancedConsumed  kWh taken from the grid in T2 after hourly balancing
     * @param  float  $offPeakBalancedFedIn  kWh sent to the grid in T2 after hourly balancing
     * @param  Price|null  $price  prices for the month (prices only change at the start of a month)
     * @param  array<string, float>  $carUsage  kWh charged per car id
     */
    public function __construct(
        public string $from,
        public string $to,
        public float $pvGenerated,
        public float $peakConsumed,
        public float $offPeakConsumed,
        public float $fedIn,
        public float $peakBalancedConsumed = 0.0,
        public float $peakBalancedFedIn = 0.0,
        public float $offPeakBalancedConsumed = 0.0,
        public float $offPeakBalancedFedIn = 0.0,
        ?Price $price = null,
        public array $carUsage = [],
    ) {
        $this->days = (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $this->consumed = $peakConsumed + $offPeakConsumed;
        $this->autoConsumed = $pvGenerated - $fedIn;
        $this->autoConsumedRatio = $pvGenerated > 0 ? $this->autoConsumed / $pvGenerated * 100 : 0.0;
        $this->totalUsage = $this->consumed + $this->autoConsumed;
        $peak = $peakBalancedConsumed - Price::FED_IN_RATIO * $peakBalancedFedIn;
        $this->peakPayable = max(0.0, $peak);
        $this->peakSurplus = max(0.0, -$peak);

        $offPeak = $offPeakBalancedConsumed - Price::FED_IN_RATIO * $offPeakBalancedFedIn - $this->peakSurplus;
        $this->offPeakPayable = max(0.0, $offPeak);
        $this->offPeakSurplus = max(0.0, -$offPeak);

        $payable = $this->peakPayable + $this->offPeakPayable;
        $this->peakPayablePercent = $payable > 0 ? $this->peakPayable / $payable * 100 : 0.0;
        $this->offPeakPayablePercent = 100 - $this->peakPayablePercent;

        $this->amount = $price ? $this->amount($price) : null;
        $daysInMonth = Carbon::parse($from)->daysInMonth;
        $this->estimatedAmount = $price && $this->days < $daysInMonth ? $this->amount / $this->days * $daysInMonth : null;
        $this->pricePerUnit = $price ? ($this->totalUsage > 0 ? $this->amount / $this->totalUsage : 0.0) : null;

        $this->carAmounts = array_map(fn (float $kWh) => $price ? $kWh * $this->pricePerUnit : null, $carUsage);
        $this->householdUsage = $this->totalUsage - array_sum($carUsage);
        $this->householdAmount = $price ? $this->amount - array_sum($this->carAmounts) : null;
    }

    private function amount(Price $price): float
    {
        $net = $price->monthlyFees() * $this->days / Carbon::parse($this->from)->daysInMonth;

        foreach (['peak' => [$this->peakPayable, $this->peakConsumed], 'off_peak' => [$this->offPeakPayable, $this->offPeakConsumed]] as $zone => [$payable, $consumed]) {
            $net += ($price->{$zone.'_sell'} + $price->{$zone.'_distr'} + $price->{$zone.'_quality'}) * $payable;
            $net += ($price->{$zone.'_oze'} + $price->{$zone.'_cogen'}) * $consumed;
        }

        return $net * Price::VAT;
    }
}
