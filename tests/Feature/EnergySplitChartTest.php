<?php

namespace Tests\Feature;

use App\Models\MeterDailyReading;
use App\Models\Price;
use App\Models\PvInverterReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EnergySplitChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_splits_total_usage_into_auto_consumed_balanced_credited_and_payable_per_zone(): void
    {
        $this->july();

        $data = Livewire::test('energy-split-chart', ['month' => '2026-07-15'])->instance()->chartData;

        // Total usage: consumed 3 + auto-consumed (PV 20 − fed-in 7) = 16; payable peak 1, off-peak 2, nothing balanced or credited.
        $this->assertSame(['Jul'], $data['categories']);
        $this->assertEquals(['autoConsumed' => [13], 'balanced' => [0], 'credited' => [0], 'offPeak' => [2], 'earlyPeak' => [0], 'peak' => [1]], $data['kwh']);
        $this->assertEquals(['autoConsumed' => [81.3], 'balanced' => [0], 'credited' => [0], 'offPeak' => [12.5], 'earlyPeak' => [0], 'peak' => [6.3]], $data['percent']);
    }

    public function test_grid_use_is_split_into_hourly_balanced_and_credited(): void
    {
        PvInverterReading::create(['date' => '2026-06-30', 'value' => 0]);
        PvInverterReading::create(['date' => '2026-07-01', 'value' => 20]);
        MeterDailyReading::create([
            'date' => '2026-07-01',
            't1_consumed' => 1, 't2_consumed' => 2,
            't1_fed_in' => 3, 't2_fed_in' => 4,
            't1_balanced_consumed' => 0, 't2_balanced_consumed' => 2,
            't1_balanced_fed_in' => 2.5, 't2_balanced_fed_in' => 0,
        ]);

        $data = Livewire::test('energy-split-chart', ['month' => '2026-07-15'])->instance()->chartData;

        // Hourly balancing cancels 1 of the 3 kWh from the grid (1:1); T1's credit 0.8 × 2.5 = 2 settles T2's 2 kWh.
        $this->assertEquals(['autoConsumed' => [13], 'balanced' => [1], 'credited' => [2], 'offPeak' => [0], 'earlyPeak' => [0], 'peak' => [0]], $data['kwh']);
    }

    public function test_three_zones_add_early_peak_payable(): void
    {
        Price::create(['since' => '2026-07-01', ...$this->twoZonePrices(0), 'early_peak_sell' => 1]);
        $this->july();

        $data = Livewire::test('energy-split-chart', ['month' => '2026-07-15'])->instance()->chartData;

        // T1 (1 kWh) is early-peak, T2 (2 kWh) peak, nothing left for off-peak; auto-consumed 13.
        $this->assertEquals(['autoConsumed' => [13], 'balanced' => [0], 'credited' => [0], 'offPeak' => [0], 'earlyPeak' => [1], 'peak' => [2]], $data['kwh']);
    }

    public function test_months_without_data_after_the_first_are_zero(): void
    {
        $this->july();
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 100]);

        $data = Livewire::test('energy-split-chart', ['month' => '2026-09-15'])->instance()->chartData;

        $this->assertSame(['Jul', 'Aug', 'Sep'], $data['categories']);
        $this->assertEquals([1, 0, 0], $data['kwh']['peak']);
        $this->assertEquals([81.3, 0, 0], $data['percent']['autoConsumed']);
    }

    public function test_dashboard_shows_the_chart(): void
    {
        $this->actingAs(User::factory()->create());
        $this->july();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Energy Split');
    }

    /** One full-data day: Jul 1 2026 with 20 kWh from PV. */
    private function july(): void
    {
        PvInverterReading::create(['date' => '2026-06-30', 'value' => 0]);
        PvInverterReading::create(['date' => '2026-07-01', 'value' => 20]);

        MeterDailyReading::create([
            'date' => '2026-07-01',
            't1_consumed' => 1, 't2_consumed' => 2,
            't1_fed_in' => 3, 't2_fed_in' => 4,
            't1_balanced_consumed' => 1, 't2_balanced_consumed' => 2,
            't1_balanced_fed_in' => 0, 't2_balanced_fed_in' => 0,
        ]);
    }
}
