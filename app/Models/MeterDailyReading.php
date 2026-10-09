<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class MeterDailyReading extends Model
{
    /**
     * Local timezone of the meter, used to know how many hours a day has (DST days have 23 or 25).
     */
    public const TIMEZONE = 'Europe/Warsaw';

    public const VALUE_FIELDS = [
        't1_consumed',
        't1_fed_in',
        't1_balanced_consumed',
        't1_balanced_fed_in',
        't2_consumed',
        't2_fed_in',
        't2_balanced_consumed',
        't2_balanced_fed_in',
    ];

    protected $fillable = [
        'date',
        'hours',
        ...self::VALUE_FIELDS,
    ];

    protected $casts = [
        'date' => 'date',
        'hours' => 'integer',
        't1_consumed' => 'float',
        't1_fed_in' => 'float',
        't1_balanced_consumed' => 'float',
        't1_balanced_fed_in' => 'float',
        't2_consumed' => 'float',
        't2_fed_in' => 'float',
        't2_balanced_consumed' => 'float',
        't2_balanced_fed_in' => 'float',
    ];

    /**
     * A row created without hours covers the whole day.
     */
    protected static function booted(): void
    {
        static::creating(function (MeterDailyReading $reading) {
            $reading->hours ??= static::dayLength($reading->date);
        });
    }

    /**
     * Number of hours in the local day: 24, or 23 / 25 on DST days.
     */
    public static function dayLength(Carbon|string $date): int
    {
        $start = Carbon::parse(Carbon::parse($date)->toDateString(), self::TIMEZONE)->startOfDay();

        return (int) $start->diffInHours($start->copy()->addDay()->startOfDay());
    }

    /**
     * Part of the day's time the meter data covers, from 0 to 1.
     */
    public function hoursFraction(): float
    {
        return min(1, $this->hours / static::dayLength($this->date));
    }

    /**
     * Estimated share of the day's PV production made within the hours the meter data covers, from 0 to 1.
     *
     * Production is modelled as a sine curve between sunrise and sunset at the PV location,
     * so its share up to a moment is (1 − cos(π·x)) / 2, x being the part of the daylight passed.
     */
    public function pvFraction(): float
    {
        $start = Carbon::parse($this->date->toDateString(), self::TIMEZONE)->startOfDay();
        // Hourly rows are real hours since midnight, also across a DST change.
        $end = $start->getTimestamp() + $this->hours * 3600;
        $noon = $start->copy()->setTime(12, 0)->getTimestamp();

        $sun = date_sun_info($noon, config('services.pv.latitude'), config('services.pv.longitude'));

        // Polar day or night: fall back to the covered part of the day.
        if (! is_int($sun['sunrise']) || ! is_int($sun['sunset'])) {
            return $this->hoursFraction();
        }

        $x = max(0, min(1, ($end - $sun['sunrise']) / ($sun['sunset'] - $sun['sunrise'])));

        return (1 - cos(M_PI * $x)) / 2;
    }
}
