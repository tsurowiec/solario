<?php

namespace App\Data;

/**
 * How one tariff zone's balanced consumption was settled in a month (kWh).
 * Credit is applied in this order: carried from the previous month, the zone's own fed-in, other zones' surplus.
 */
readonly class ZoneFlow
{
    /** kWh left to pay for: consumed − all credit applied, never below 0. */
    public float $payable;

    /**
     * @param  string  $zone  tariff zone (Price::ZONES)
     * @param  float  $fedIn  kWh sent to the grid after hourly balancing
     * @param  float  $consumed  kWh taken from the grid after hourly balancing
     * @param  float  $fromPreviousMonth  credit carried from the previous month applied here
     * @param  float  $fromOwn  the zone's own credit (Price::FED_IN_RATIO × fed-in) applied here
     * @param  float  $fromOtherZones  other zones' surplus applied here
     * @param  float  $toPool  the zone's own credit left after its own consumption, moved to the pool
     *                         for the other zones (and, if they don't need it, the next month)
     */
    public function __construct(
        public string $zone,
        public float $fedIn,
        public float $consumed,
        public float $fromPreviousMonth,
        public float $fromOwn,
        public float $fromOtherZones,
        public float $toPool = 0.0,
    ) {
        $this->payable = max(0.0, $consumed - $fromPreviousMonth - $fromOwn - $fromOtherZones);
    }
}
