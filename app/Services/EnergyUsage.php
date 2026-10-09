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
 *
 * A day has full data when it has a meter row and the PV inverter counter can be
 * determined for that day and the day before (a reading is the counter at the end of its day):
 * interpolated between readings, or extrapolated after the last one when there are at least two.
 */
class EnergyUsage
{
    public function __construct(private readonly PvInverterInterpolator $interpolator) {}

    /**
     * Summary for the month of the given date: from its first full-data day
     * up to the day before the first gap. Null when the month has no full-data day.
     */
    public function month(Carbon $date): ?EnergySummary
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

        $days = $meter->filter(fn (MeterDailyReading $r) => $r->date->betweenIncluded($from, $to));

        return new EnergySummary(
            from: $from->toDateString(),
            to: $to->toDateString(),
            // The last day may be only partly covered by meter data, so PV is counted up to that point.
            pvGenerated: $this->interpolator->forDate($to, $meter[$to->toDateString()]->dayFraction())
                - $this->interpolator->forDate($from->copy()->subDay()),
            peakConsumed: $days->sum('t1_consumed'),
            offPeakConsumed: $days->sum('t2_consumed'),
            fedIn: $days->sum(fn (MeterDailyReading $r) => $r->t1_fed_in + $r->t2_fed_in),
            peakBalancedConsumed: $days->sum('t1_balanced_consumed'),
            peakBalancedFedIn: $days->sum('t1_balanced_fed_in'),
            offPeakBalancedConsumed: $days->sum('t2_balanced_consumed'),
            offPeakBalancedFedIn: $days->sum('t2_balanced_fed_in'),
            price: Price::activeOn($from),
            carUsage: $this->carUsage($from, $to),
        );
    }

    /**
     * kWh charged per car in the period (every car in CarCharge::CARS, 0 when none).
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
            ->mapWithKeys(fn (string $car) => [$car => (float) ($charged[$car] ?? 0)])
            ->all();
    }
}
