<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    /** Prices in PLN per kWh. */
    public const KWH_FIELDS = [
        'peak_sell',
        'early_peak_sell',
        'off_peak_sell',
        'peak_distr',
        'early_peak_distr',
        'off_peak_distr',
        'peak_quality',
        'early_peak_quality',
        'off_peak_quality',
        'peak_oze',
        'early_peak_oze',
        'off_peak_oze',
        'peak_cogen',
        'early_peak_cogen',
        'off_peak_cogen',
    ];

    /** Per-kWh fees of each tariff zone (prefixed with the zone: peak_sell, ...). */
    public const FEES = ['sell', 'distr', 'quality', 'oze', 'cogen'];

    /** Tariff zones, most expensive first: the order surplus credit is applied in. */
    public const ZONES = ['peak', 'early_peak', 'off_peak'];

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
     * Whether the three-zone tariff applies: any early-peak price above 0.
     */
    public function hasEarlyPeak(): bool
    {
        return collect(self::FEES)->contains(fn (string $fee) => $this->{'early_peak_'.$fee} > 0);
    }

    /**
     * Meter zone columns (t1, t2, t3) per tariff zone, in Price::ZONES order.
     * Three zones: T1 morning = early-peak, T2 evening = peak, T3 = off-peak.
     * Two zones (or no prices): T1 = peak, T2 = off-peak, with T3 counted as off-peak too
     * and early-peak empty, so meter data imported before the new prices keeps its totals.
     *
     * @return array{peak: list<string>, early_peak: list<string>, off_peak: list<string>}
     */
    public static function meterZones(?self $price): array
    {
        return $price?->hasEarlyPeak()
            ? ['peak' => ['t2'], 'early_peak' => ['t1'], 'off_peak' => ['t3']]
            : ['peak' => ['t1'], 'early_peak' => [], 'off_peak' => ['t2', 't3']];
    }

    /**
     * Approximate gross price per kWh for a tariff zone ('peak', 'early_peak' or 'off_peak'):
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
