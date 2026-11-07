<?php

namespace App\Data;

use App\Models\CarCharge;
use App\Models\Price;
use Carbon\Carbon;

readonly class EnergySummary
{
    public float $autoConsumed;

    public float $autoConsumedRatio;

    public float $totalUsage;

    /** kWh taken from the grid (raw, before balancing), all zones. */
    public float $consumed;

    /** Whether the three-zone tariff applies (Price::hasEarlyPeak()); with two zones early-peak stays empty. */
    public bool $hasEarlyPeak;

    /**
     * Settlement per tariff zone, keyed and ordered by Price::ZONES (most expensive first):
     * 1. credit carried from the previous month ($creditIn) is applied, most expensive zone first;
     * 2. each zone's own credit (Price::FED_IN_RATIO × balanced fed-in) lowers its own balanced consumed;
     * 3. the zones' surplus and any carried credit left are pooled and applied, most expensive zone first;
     * 4. what is still left carries to the next month ($creditOut).
     *
     * @var array<string, ZoneFlow>
     */
    public array $flows;

    /** kWh to pay for in the peak zone. */
    public float $peakPayable;

    /** kWh to pay for in the early-peak zone (0 with two zones). */
    public float $earlyPeakPayable;

    /** kWh to pay for in the off-peak zone. */
    public float $offPeakPayable;

    /** Credit left after settling every zone; it carries to the next month. */
    public float $creditOut;

    /** Number of days in the period, both ends included. */
    public int $days;

    /** Days the meter data covers: $days with a partial last day counted by its covered hours. */
    public float $coveredDays;

    /** Days of PV production counted: $days with a partial last day counted by its share of the day's PV. */
    public float $pvDays;

    /**
     * Gross cost in PLN incl. VAT, calculated like the invoice; null without prices.
     * One row per fee: each monthly fee prorated to the days the period covers ($coveredDays),
     * and per zone (early-peak only with three zones) sell, distr and quality × payable, oze and cogen × balanced consumed.
     * kWh are rounded to whole numbers, each row's net and gross to 0.01 PLN; the amount is the sum of the rows.
     */
    public ?float $amount;

    /** Amount projected to the whole month (amount ÷ covered days × days in month); null without prices or when the period covers the full month. */
    public ?float $estimatedAmount;

    /** Total usage projected to the whole month (total usage ÷ covered days × days in month); null when the period covers the full month. */
    public ?float $estimatedUsage;

    /** Amount ÷ total usage (PLN/kWh); null without prices. */
    public ?float $pricePerUnit;

    /** @var array<string, float|null> PLN per car (kWh taken from the grid × price per kWh); null without prices. */
    public array $carAmounts;

    /** @var array<string, float> km of range added per car (kWh charged at the car's consumption). */
    public array $carRange;

    /** @var array<string, float|null> PLN per 100 km of range per car; null without prices or range. */
    public array $carCostPer100Km;

    /** kWh used by the household: total usage − all car usage. */
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
     * @param  array<string, float>  $carUsage  kWh taken from the grid per car id
     * @param  float  $lastDayHoursFraction  part of the last day's time the meter data covers (MeterDailyReading::hoursFraction())
     * @param  float  $lastDayPvFraction  share of the last day's PV production counted (MeterDailyReading::pvFraction())
     * @param  float  $earlyPeakConsumed  kWh taken from the grid in early-peak (raw, before balancing)
     * @param  float  $earlyPeakBalancedConsumed  kWh taken from the grid in early-peak after hourly balancing
     * @param  float  $earlyPeakBalancedFedIn  kWh sent to the grid in early-peak after hourly balancing
     * @param  float  $creditIn  credit carried from the previous month (kWh, after Price::FED_IN_RATIO)
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
        float $lastDayHoursFraction = 1.0,
        float $lastDayPvFraction = 1.0,
        public float $earlyPeakConsumed = 0.0,
        public float $earlyPeakBalancedConsumed = 0.0,
        public float $earlyPeakBalancedFedIn = 0.0,
        public float $creditIn = 0.0,
    ) {
        $this->days = (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $this->coveredDays = $this->days - 1 + $lastDayHoursFraction;
        $this->pvDays = $this->days - 1 + $lastDayPvFraction;
        $this->consumed = $peakConsumed + $earlyPeakConsumed + $offPeakConsumed;
        $this->autoConsumed = $pvGenerated - $fedIn;
        $this->autoConsumedRatio = $pvGenerated > 0 ? $this->autoConsumed / $pvGenerated * 100 : 0.0;
        $this->totalUsage = $this->consumed + $this->autoConsumed;
        $this->hasEarlyPeak = $price?->hasEarlyPeak() ?? false;
        [$this->flows, $this->creditOut] = self::settle([
            'peak' => [$peakBalancedConsumed, $peakBalancedFedIn],
            'early_peak' => [$earlyPeakBalancedConsumed, $earlyPeakBalancedFedIn],
            'off_peak' => [$offPeakBalancedConsumed, $offPeakBalancedFedIn],
        ], $creditIn);
        $this->peakPayable = $this->flows['peak']->payable;
        $this->earlyPeakPayable = $this->flows['early_peak']->payable;
        $this->offPeakPayable = $this->flows['off_peak']->payable;

        $this->amount = $price ? $this->amount($price) : null;
        $daysInMonth = Carbon::parse($from)->daysInMonth;
        $partial = $this->coveredDays < $daysInMonth && $this->coveredDays > 0;
        $this->estimatedAmount = $price && $partial ? $this->amount / $this->coveredDays * $daysInMonth : null;
        $this->estimatedUsage = $partial ? $this->totalUsage / $this->coveredDays * $daysInMonth : null;
        $this->pricePerUnit = $price ? ($this->totalUsage > 0 ? $this->amount / $this->totalUsage : 0.0) : null;

        $this->carAmounts = array_map(fn (float $kWh) => $price ? $kWh * $this->pricePerUnit : null, $carUsage);
        $this->carRange = array_combine(array_keys($carUsage), array_map(
            fn (float $kWh, string $car) => $kWh / CarCharge::GRID_RATIO / CarCharge::CONSUMPTION[$car] * 100,
            $carUsage,
            array_keys($carUsage),
        ));
        $this->carCostPer100Km = array_combine(array_keys($carUsage), array_map(
            fn (?float $amount, float $km) => $amount !== null && $km > 0 ? $amount / $km * 100 : null,
            $this->carAmounts,
            $this->carRange,
        ));
        $this->householdUsage = $this->totalUsage - array_sum($carUsage);
        $this->householdAmount = $price ? $this->amount - array_sum($this->carAmounts) : null;
    }

    /**
     * Daily average of a value measured over the covered time (grid, usage, costs); 0 when nothing is covered.
     */
    public function perDay(float $value): float
    {
        return $this->coveredDays > 0 ? $value / $this->coveredDays : 0.0;
    }

    /**
     * Daily average of a value that follows PV production (generated, self-consumed); 0 when no PV is counted.
     */
    public function perPvDay(float $value): float
    {
        return $this->pvDays > 0 ? $value / $this->pvDays : 0.0;
    }

    /**
     * Settles the zones as described at $flows.
     *
     * @param  array<string, array{float, float}>  $balanced  [balanced consumed, balanced fed-in] per zone, most expensive first
     * @return array{array<string, ZoneFlow>, float} the flows and the credit left
     */
    private static function settle(array $balanced, float $credit): array
    {
        $left = array_map(fn (array $zone) => $zone[0], $balanced);

        $fromPrevious = self::spend($left, $credit);

        $fromOwn = [];
        $toPool = [];
        foreach ($balanced as $zone => [, $fedIn]) {
            $own = Price::FED_IN_RATIO * $fedIn;
            $fromOwn[$zone] = min($left[$zone], $own);
            $left[$zone] -= $fromOwn[$zone];
            $toPool[$zone] = $own - $fromOwn[$zone];
            $credit += $toPool[$zone];
        }

        $fromOther = self::spend($left, $credit);

        $flows = [];
        foreach ($balanced as $zone => [$consumed, $fedIn]) {
            $flows[$zone] = new ZoneFlow($zone, $fedIn, $consumed, $fromPrevious[$zone], $fromOwn[$zone], $fromOther[$zone], $toPool[$zone]);
        }

        return [$flows, $credit];
    }

    /**
     * Applies credit to what is left in each zone, in the given order; both are updated.
     *
     * @param  array<string, float>  $left  kWh left to pay for per zone
     * @return array<string, float> kWh of credit applied per zone
     */
    private static function spend(array &$left, float &$credit): array
    {
        $used = [];
        foreach ($left as $zone => $kWh) {
            $used[$zone] = min($kWh, $credit);
            $left[$zone] -= $used[$zone];
            $credit -= $used[$zone];
        }

        return $used;
    }

    private function amount(Price $price): float
    {
        $monthShare = $this->coveredDays / Carbon::parse($this->from)->daysInMonth;
        $rows = array_map(fn (string $field) => $price->$field * $monthShare, Price::MONTHLY_FIELDS);

        foreach ($this->flows as $zone => $flow) {
            // Two-zone prices have no early-peak rows (its flow is empty then anyway).
            if ($zone === 'early_peak' && ! $this->hasEarlyPeak) {
                continue;
            }

            foreach (['sell', 'distr', 'quality'] as $fee) {
                $rows[] = $price->{$zone.'_'.$fee} * round($flow->payable);
            }

            foreach (['oze', 'cogen'] as $fee) {
                $rows[] = $price->{$zone.'_'.$fee} * round($flow->consumed);
            }
        }

        return round(array_sum(array_map(fn (float $net) => round(round($net, 2) * Price::VAT, 2), $rows)), 2);
    }
}
