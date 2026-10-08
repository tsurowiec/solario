<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    /** Prices in PLN per kWh. */
    public const KWH_FIELDS = [
        'peak_sell',
        'off_peak_sell',
        'peak_distr',
        'off_peak_distr',
        'peak_quality',
        'off_peak_quality',
        'peak_oze',
        'off_peak_oze',
        'peak_cogen',
        'off_peak_cogen',
    ];

    /** Fixed fees in PLN per month. */
    public const MONTHLY_FIELDS = [
        'sell_monthly',
        'power_monthly',
        'subscription_monthly',
        'network_monthly',
    ];

    public const VAT = 1.23;

    /** Share of balanced fed-in energy that offsets balanced consumption (net-metering). */
    public const FED_IN_RATIO = 0.8;

    protected $fillable = [
        'since',
        ...self::KWH_FIELDS,
        ...self::MONTHLY_FIELDS,
    ];

    protected function casts(): array
    {
        return [
            'since' => 'date',
            ...array_fill_keys([...self::KWH_FIELDS, ...self::MONTHLY_FIELDS], 'float'),
        ];
    }

    /**
     * The prices in force on the given date (the latest set that started by then).
     */
    public static function activeOn(CarbonInterface $date): ?self
    {
        return static::whereDate('since', '<=', $date)
            ->orderByDesc('since')
            ->first();
    }

    /**
     * Approximate gross price per kWh for a tariff zone ('peak' or 'off_peak'):
     * (sell + distr + quality + 2 × oze + 2 × cogen) × VAT.
     */
    public function grossPerKwh(string $zone): float
    {
        return ($this->{$zone.'_sell'}
            + $this->{$zone.'_distr'}
            + $this->{$zone.'_quality'}
            + 2 * $this->{$zone.'_oze'}
            + 2 * $this->{$zone.'_cogen'}) * self::VAT;
    }

    /**
     * Sum of the monthly fees (net, PLN per month).
     */
    public function monthlyFees(): float
    {
        return array_sum(array_map(fn (string $field) => $this->$field, self::MONTHLY_FIELDS));
    }

    /**
     * Sum of the monthly fees incl. VAT (PLN per month).
     */
    public function grossMonthly(): float
    {
        return $this->monthlyFees() * self::VAT;
    }
}
