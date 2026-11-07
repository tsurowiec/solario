<?php

namespace Tests\Feature;

use App\Models\MeterDailyReading;
use App\Models\Price;
use App\Models\PvInverterReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MonthFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_zones_show_the_settlement_per_zone_rounded(): void
    {
        // Two days: peak 1 consumed / 5 fed-in, off-peak 1 / 2 a day (balanced).
        $this->meterDays(['t1_balanced_consumed' => 1, 't1_balanced_fed_in' => 5, 't2_balanced_consumed' => 1, 't2_balanced_fed_in' => 2]);

        // Peak: own credit 8 covers 2, 6 to the pool; off-peak: own credit 3.2 covers 2, 1.2 to the pool; credit out 7.2 → 7.
        // Zones are rows, fields are columns; zeros are shown too.
        Livewire::test('month-flows', ['month' => '2026-10-01'])
            ->assertSee('October 2026 - Flows')
            ->assertDontSee('Early-Peak')
            ->assertSeeInOrder(['Fed-in', 'Consumed', 'Minus Prev Month', 'Minus Own', 'Minus Other Zones', 'Moved to Pool', 'Payable'])
            ->assertSeeInOrder([
                'title="Peak"', '>10<', '>2<', '>0<', '>2<', '>0<', '>6<', '>0<',
                'title="Off-Peak"', '>4<', '>2<', '>0<', '>2<', '>0<', '>1<', '>0<',
                'Credit from the previous month', '>0<', 'Credit to the next month', '>7<',
            ], false);
    }

    public function test_three_zones_show_columns_in_time_of_day_order(): void
    {
        Price::create(['since' => '2026-10-01', ...$this->twoZonePrices(0), 'early_peak_sell' => 1]);
        // T1 morning = early-peak, T2 evening = peak, T3 = off-peak.
        $this->meterDays(['t1_balanced_consumed' => 11, 't2_balanced_consumed' => 22, 't3_balanced_consumed' => 33]);

        Livewire::test('month-flows', ['month' => '2026-10-01'])
            ->assertSeeInOrder([
                'title="Early-Peak"', '>0<', '>22<', '>0<', '>0<', '>0<', '>0<', '>22<',
                'title="Peak"', '>0<', '>44<', '>0<', '>0<', '>0<', '>0<', '>44<',
                'title="Off-Peak"', '>0<', '>66<', '>0<', '>0<', '>0<', '>0<', '>66<',
            ], false);
    }

    public function test_month_without_data_shows_a_message(): void
    {
        Livewire::test('month-flows', ['month' => '2026-10-01'])
            ->assertSee('October 2026 - Flows')
            ->assertSee('No data for this period.');
    }

    private function meterDays(array $values): void
    {
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-10', 'value' => 1200]);

        foreach (['2026-10-01', '2026-10-02'] as $date) {
            MeterDailyReading::create(['date' => $date, ...array_fill_keys(MeterDailyReading::VALUE_FIELDS, 0), ...$values]);
        }
    }
}
