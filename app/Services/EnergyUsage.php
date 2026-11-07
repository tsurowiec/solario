<?php

namespace App\Services;

use App\Data\EnergySummary;
use App\Models\CarCharge;
use App\Models\MeterDailyReading;
use App\Models\Price;
use App\Models\PvInverterReading;
use Carbon\Carbon;

/**
 * Combines MeterDailyReading (grid) and PV inverter readings into an EnergySummary.
 * Meter zones map to tariff zones by the month's prices (Price::meterZones()), and credit
 * left after a month's settlement carries to the next month.
 *
 * A day has full data when it has a meter row and the PV inverter counter can be
 * determined for that day and the day before (a reading is the counter at the end of its day):
 * interpolated between readings, or extrapolated after the last one when there are at least two.
 */
class EnergyUsage
{
    /** @var array<string, float> credit carried out of each month (Y-m) worked out so far */
    private array $credits = [];

    private ?string $firstMeterMonth = null;

    public function __construct(private readonly PvInverterInterpolator $interpolator) {}

    /**
     * Summary for the month of the given date: from its first full-data day
     * up to the day before the first gap. Null when the month has no full-data day.
     */
    public function month(Carbon $date): ?EnergySummary
    {
        return $this->summary($date, $this->creditBefore($date));
    }

    private function summary(Carbon $date, float $creditIn): ?EnergySummary
    {
        $start = $date->copy()->startOfMonth()->startOfDay();
        $end = $date->copy()->endOfMonth()->startOfDay();

        $firstPv = PvInverterReading::oldest('date')->value('date');
        $lastPv = PvInverterReading::latest('date')->value('date');

        if (! $firstPv || ! $lastPv) {
            return null;
        }

        $firstPv = Carbon::parse($firstPv)->startOfDay();
        // With two or more readings the counter is extrapolated past the last one.
        $lastPv = $this->interpolator->extrapolates() ? $end : Carbon::parse($lastPv)->startOfDay();

        $meter = MeterDailyReading::whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn (MeterDailyReading $r) => $r->date->toDateString());

        $isFull = fn (Carbon $day) => $meter->has($day->toDateString())
            && $day->copy()->subDay()->gte($firstPv)
            && $day->lte($lastPv);

        $from = null;
        $to = null;

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($isFull($day)) {
                $from ??= $day->copy();
                $to = $day->copy();
            } elseif ($from) {
                break;
            }
        }

        if (! $from) {
            return null;
        }

        $last = $meter[$to->toDateString()];
        $days = $meter->filter(fn (MeterDailyReading $r) => $r->date->betweenIncluded($from, $to));

        $price = Price::activeOn($from);
        $zones = Price::meterZones($price);
        // Sum of a field (consumed, fed_in, ...) over the meter zones (t1, t2, t3) a tariff zone maps to.
        $sum = fn (string $zone, string $field) => (float) $days->sum(
            fn (MeterDailyReading $r) => array_sum(array_map(fn (string $t) => $r->{$t.'_'.$field}, $zones[$zone])),
        );

        return new EnergySummary(
            from: $from->toDateString(),
            to: $to->toDateString(),
            // The last day may be only partly covered by meter data, so PV is counted up to that point.
            pvGenerated: $this->interpolator->forDate($to, $last->pvFraction())
                - $this->interpolator->forDate($from->copy()->subDay()),
            peakConsumed: $sum('peak', 'consumed'),
            offPeakConsumed: $sum('off_peak', 'consumed'),
            fedIn: $days->sum(fn (MeterDailyReading $r) => $r->t1_fed_in + $r->t2_fed_in + $r->t3_fed_in),
            peakBalancedConsumed: $sum('peak', 'balanced_consumed'),
            peakBalancedFedIn: $sum('peak', 'balanced_fed_in'),
            offPeakBalancedConsumed: $sum('off_peak', 'balanced_consumed'),
            offPeakBalancedFedIn: $sum('off_peak', 'balanced_fed_in'),
            price: $price,
            carUsage: $this->carUsage($from, $to),
            lastDayHoursFraction: $last->hoursFraction(),
            lastDayPvFraction: $last->pvFraction(),
            earlyPeakConsumed: $sum('early_peak', 'consumed'),
            earlyPeakBalancedConsumed: $sum('early_peak', 'balanced_consumed'),
            earlyPeakBalancedFedIn: $sum('early_peak', 'balanced_fed_in'),
            creditIn: $creditIn,
        );
    }

    /**
     * Credit carried into the month of the given date: the previous month's credit out,
     * worked forward from the first meter month (opening credit 0). A month without a summary passes it on.
     */
    private function creditBefore(Carbon $date): float
    {
        $previous = $date->copy()->startOfMonth()->startOfDay()->subMonthNoOverflow();
        $key = $previous->format('Y-m');

        if (array_key_exists($key, $this->credits)) {
            return $this->credits[$key];
        }

        $this->firstMeterMonth ??= Carbon::parse(MeterDailyReading::oldest('date')->value('date') ?? 'now')
            ->startOfMonth()->format('Y-m');

        if ($key < $this->firstMeterMonth) {
            return 0.0;
        }

        $creditIn = $this->creditBefore($previous);

        return $this->credits[$key] = $this->summary($previous, $creditIn)?->creditOut ?? $creditIn;
    }

    /**
     * kWh taken from the grid per car in the period (charged × CarCharge::GRID_RATIO; every car in CarCharge::CARS, 0 when none).
     *
     * @return array<string, float>
     */
    private function carUsage(Carbon $from, Carbon $to): array
    {
        $charged = CarCharge::whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->selectRaw('car_id, sum(charged) as kwh')
            ->groupBy('car_id')
            ->pluck('kwh', 'car_id');

        return collect(CarCharge::CARS)
            ->mapWithKeys(fn (string $car) => [$car => ($charged[$car] ?? 0) * CarCharge::GRID_RATIO])
            ->all();
    }
}
