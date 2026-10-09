<?php

namespace Tests\Feature;

use App\Models\MeterDailyReading;
use App\Models\PvInverterReading;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MonthlyChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_shows_the_last_12_months(): void
    {
        $this->monthsWithData('2025-06-01', 16);

        $data = Livewire::test('monthly-chart', ['month' => '2026-09-15'])
            ->assertSee('Oct 2025 – Sep 2026')
            ->instance()->chartData;

        $this->assertSame(['Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'], $data['categories']);
        $this->assertSame(array_fill(0, 12, 3.0), $data['consumed']);
    }

    public function test_chart_shows_fewer_months_when_less_data_is_available(): void
    {
        $this->monthsWithData('2026-07-01', 3);

        $data = Livewire::test('monthly-chart', ['month' => '2026-09-15'])
            ->assertSee('Jul 2026 – Sep 2026')
            ->instance()->chartData;

        $this->assertSame(['Jul', 'Aug', 'Sep'], $data['categories']);
    }

    public function test_gaps_after_the_first_month_are_shown_as_zero(): void
    {
        $this->monthsWithData('2026-07-01', 3);
        MeterDailyReading::whereDate('date', '2026-08-01')->delete();

        $data = Livewire::test('monthly-chart', ['month' => '2026-09-15'])->instance()->chartData;

        $this->assertSame(['Jul', 'Aug', 'Sep'], $data['categories']);
        $this->assertSame([3.0, 0.0, 3.0], $data['consumed']);
    }

    public function test_dashboard_shows_the_chart(): void
    {
        $this->actingAs(User::factory()->create());
        $this->monthsWithData('2026-07-01', 3);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Jul 2026 – Sep 2026');
    }

    /** One meter day (the 1st) per month, with PV readings covering the whole range. */
    private function monthsWithData(string $from, int $months): void
    {
        $first = Carbon::parse($from);

        PvInverterReading::create(['date' => $first->copy()->subDay()->toDateString(), 'value' => 0]);
        PvInverterReading::create(['date' => $first->copy()->addMonths($months)->toDateString(), 'value' => 10000]);

        for ($i = 0; $i < $months; $i++) {
            MeterDailyReading::create([
                'date' => $first->copy()->addMonths($i)->toDateString(),
                't1_consumed' => 1, 't2_consumed' => 2,
                't1_fed_in' => 3, 't2_fed_in' => 4,
                't1_balanced_consumed' => 0, 't2_balanced_consumed' => 0,
                't1_balanced_fed_in' => 0, 't2_balanced_fed_in' => 0,
            ]);
        }
    }
}
