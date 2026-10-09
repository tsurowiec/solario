<?php

namespace App\Services;

use App\Models\PvInverterReading;
use Carbon\Carbon;
use InvalidArgumentException;

class PvInverterInterpolator
{
    /**
     * Interpolate the PV inverter value for the given date.
     *
     * The value is linearly proportioned between the nearest readings
     * on either side of the date. After the last reading it is extrapolated
     * at the daily rate of the last two readings.
     *
     * With $dayFraction below 1 (a day whose meter data covers only part of it), the value is
     * estimated that far into the day: the previous day's value plus that part of the day's production.
     *
     * @throws InvalidArgumentException if the date is before the first reading, or after the last one when there is only one
     */
    public function forDate(Carbon $date, float $dayFraction = 1.0): float
    {
        if ($dayFraction < 1) {
            $previous = $this->forDate($date->copy()->subDay());

            return $previous + ($this->forDate($date) - $previous) * $dayFraction;
        }

        $before = PvInverterReading::whereDate('date', '<=', $date->toDateString())
            ->orderByDesc('date')
            ->first();

        $after = PvInverterReading::whereDate('date', '>=', $date->toDateString())
            ->orderBy('date')
            ->first();

        if ($before && ! $after) {
            $previous = PvInverterReading::whereDate('date', '<', $before->date->toDateString())
                ->orderByDesc('date')
                ->first();

            if ($previous) {
                return $this->extrapolate($previous, $before, $date);
            }
        }

        if (! $before || ! $after) {
            throw new InvalidArgumentException(
                "Date {$date->toDateString()} is outside the range of PV inverter readings."
            );
        }

        // Exact match — no interpolation needed.
        if ($before->date->eq($after->date)) {
            return (float) $before->value;
        }

        $totalDays = $before->date->diffInDays($after->date);
        $elapsed = $before->date->diffInDays($date->copy()->startOfDay());

        return $before->value + ($after->value - $before->value) * $elapsed / $totalDays;
    }

    /**
     * Interpolated values for every day in the range, keyed by date (Y-m-d).
     * Days before the first reading are left out; days after the last one are
     * extrapolated as in forDate() (left out when there is only one reading).
     * Loads the readings once, so it is suited for listing many days.
     * Days in $dayFractions (Y-m-d => fraction) are estimated part way into the day as in forDate().
     *
     * @param  array<string, float>  $dayFractions
     * @return array<string, float>
     */
    public function between(Carbon $from, Carbon $to, array $dayFractions = []): array
    {
        $values = $this->endOfDayValues($from->copy()->subDay(), $to);

        foreach ($dayFractions as $date => $fraction) {
            $previous = Carbon::parse($date)->subDay()->toDateString();

            if ($fraction < 1 && isset($values[$date], $values[$previous])) {
                $values[$date] = $values[$previous] + ($values[$date] - $values[$previous]) * $fraction;
            }
        }

        unset($values[$from->copy()->subDay()->toDateString()]);

        return $values;
    }

    /**
     * Values at the end of every day in the range, as described in between().
     *
     * @return array<string, float>
     */
    private function endOfDayValues(Carbon $from, Carbon $to): array
    {
        $readings = PvInverterReading::orderBy('date')->get()->values();
        $values = [];

        if ($readings->isEmpty()) {
            return $values;
        }

        $i = 0;

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            while ($i + 1 < $readings->count() && $readings[$i + 1]->date->lte($day)) {
                $i++;
            }

            $before = $readings[$i];

            if ($before->date->gt($day)) {
                continue;
            }

            if ($before->date->eq($day)) {
                $values[$day->toDateString()] = (float) $before->value;

                continue;
            }

            $after = $readings[$i + 1] ?? null;

            if (! $after) {
                if ($i === 0) {
                    break;
                }

                $values[$day->toDateString()] = $this->extrapolate($readings[$i - 1], $before, $day);

                continue;
            }

            $values[$day->toDateString()] = $before->value
                + ($after->value - $before->value) * $before->date->diffInDays($day) / $before->date->diffInDays($after->date);
        }

        return $values;
    }

    /**
     * Whether values after the last reading can be extrapolated (needs at least two readings).
     */
    public function extrapolates(): bool
    {
        return PvInverterReading::count() >= 2;
    }

    /**
     * Value on a date after $last, continuing at the daily rate between $previous and $last.
     */
    private function extrapolate(PvInverterReading $previous, PvInverterReading $last, Carbon $date): float
    {
        $rate = ($last->value - $previous->value) / $previous->date->diffInDays($last->date);

        return $last->value + $rate * $last->date->diffInDays($date->copy()->startOfDay());
    }
}
