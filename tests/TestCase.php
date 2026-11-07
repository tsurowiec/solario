<?php

namespace Tests;

use App\Models\Price;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every price field set to $value, except early-peak (0), so the two-zone tariff applies.
     *
     * @return array<string, float>
     */
    protected function twoZonePrices(float $value): array
    {
        return [
            ...array_fill_keys([...Price::KWH_FIELDS, ...Price::MONTHLY_FIELDS], $value),
            ...array_fill_keys(array_map(fn (string $fee) => 'early_peak_'.$fee, Price::FEES), 0),
        ];
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
