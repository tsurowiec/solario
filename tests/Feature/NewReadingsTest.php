<?php

namespace Tests\Feature;

use App\Models\MeterDailyReading;
use App\Models\PvInverterReading;
use App\Models\User;
use App\Services\PvInverterInterpolator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NewReadingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        auth()->logout();

        $this->get(route('new.readings.index'))->assertRedirect(route('login'));
    }

    public function test_lists_days_newest_first_combining_meter_and_pv(): void
    {
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-04', 'value' => 1040]);
        $this->meterDay('2026-10-02', 1.234);
        $this->meterDay('2026-10-05', 9.876);

        Livewire::test('pages::new.readings.index')
            ->assertSeeInOrder(['Mon, 05 Oct 2026', 'Sun, 04 Oct 2026', 'Sat, 03 Oct 2026', 'Fri, 02 Oct 2026', 'Thu, 01 Oct 2026', 'Wed, 30 Sep 2026'])
            ->assertSee('9.876')           // meter values shown
            ->assertSee('1.234')
            ->assertSee('1,040')           // actual PV reading
            ->assertSee('~1,020.0');       // interpolated PV counter on Oct 2
    }

    public function test_meter_columns_are_grouped_by_tariff_in_field_order(): void
    {
        MeterDailyReading::create([
            'date' => '2026-10-01',
            't1_consumed' => 1.111, 't1_fed_in' => 2.222, 't1_balanced_consumed' => 3.333, 't1_balanced_fed_in' => 4.444,
            't2_consumed' => 5.555, 't2_fed_in' => 6.666, 't2_balanced_consumed' => 7.777, 't2_balanced_fed_in' => 8.888,
        ]);

        Livewire::test('pages::new.readings.index')
            ->assertSeeInOrder(['Peak (T1)', 'Off-Peak (T2)', 'Measured', 'Balanced', 'Measured', 'Balanced', 'Consumed', 'Fed-in', 'Consumed', 'Fed-in', 'Consumed', 'Fed-in', 'Consumed', 'Fed-in', 'PV counter'])
            ->assertSeeInOrder(['1.111', '2.222', '3.333', '4.444', '5.555', '6.666', '7.777', '8.888']);
    }

    public function test_only_actual_pv_readings_are_editable(): void
    {
        $actual = PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-04', 'value' => 1040]);

        $response = Livewire::test('pages::new.readings.index');

        $response->assertSee(route('new.pv-inverter.edit', $actual));
        $this->assertSame(2, preg_match_all('~/new/pv-inverter/\d+/edit~', $response->html()));
    }

    public function test_days_are_paginated_by_31(): void
    {
        PvInverterReading::create(['date' => '2026-01-01', 'value' => 0]);
        PvInverterReading::create(['date' => '2026-03-31', 'value' => 900]);

        Livewire::test('pages::new.readings.index')
            ->assertSee('Tue, 31 Mar 2026')
            ->assertSee('Sun, 01 Mar 2026')
            ->assertDontSee('Sat, 28 Feb 2026')
            ->call('gotoPage', 2)
            ->assertSee('Sat, 28 Feb 2026')
            ->assertDontSee('Sun, 01 Mar 2026');
    }

    public function test_pv_reading_can_be_edited(): void
    {
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        $reading = PvInverterReading::create(['date' => '2026-10-04', 'value' => 1040]);

        Livewire::test('pages::new.pv-inverter.edit', ['reading' => $reading])
            ->set('date', '2026-10-05')
            ->set('value', 1050)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('new.readings.index'));

        $reading->refresh();
        $this->assertSame('2026-10-05', $reading->date->toDateString());
        $this->assertSame(1050, $reading->value);
    }

    public function test_edited_reading_must_stay_between_neighbours(): void
    {
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        $reading = PvInverterReading::create(['date' => '2026-10-04', 'value' => 1040]);
        PvInverterReading::create(['date' => '2026-10-10', 'value' => 1100]);

        Livewire::test('pages::new.pv-inverter.edit', ['reading' => $reading])
            ->set('date', '2026-10-10')
            ->set('value', 999)
            ->call('save')
            ->assertHasErrors(['date', 'value']);

        Livewire::test('pages::new.pv-inverter.edit', ['reading' => $reading])
            ->set('value', 1101)
            ->call('save')
            ->assertHasErrors('value');
    }

    public function test_bulk_interpolation_matches_single_day_interpolation(): void
    {
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-07', 'value' => 1100]);
        PvInverterReading::create(['date' => '2026-10-10', 'value' => 1130]);
        $interpolator = app(PvInverterInterpolator::class);

        $values = $interpolator->between(Carbon::parse('2026-09-28'), Carbon::parse('2026-10-12'));

        $this->assertSame('2026-09-30', array_key_first($values));
        $this->assertSame('2026-10-10', array_key_last($values));
        foreach ($values as $date => $value) {
            $this->assertEqualsWithDelta($interpolator->forDate(Carbon::parse($date)), $value, 0.0001, $date);
        }
    }

    private function meterDay(string $date, float $value): void
    {
        MeterDailyReading::create(['date' => $date, ...array_fill_keys(MeterDailyReading::VALUE_FIELDS, $value)]);
    }
}
