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

class ReadingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-06');
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        auth()->logout();

        $this->get(route('readings.index'))->assertRedirect(route('login'));
    }

    public function test_lists_days_newest_first_combining_meter_and_pv(): void
    {
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-04', 'value' => 1040]);
        $this->meterDay('2026-10-02', 1.234);
        $this->meterDay('2026-10-05', 9.876);

        Livewire::test('pages::readings.index')
            ->assertSeeInOrder(['2026-10-05', '2026-10-04', '2026-10-03', '2026-10-02', '2026-10-01'])
            ->assertDontSee('2026-09-30')   // previous month
            ->assertSee('9.876')           // meter values shown
            ->assertSee('1.234')
            ->assertSee('1,040')           // actual PV counter reading
            ->assertSee('~1,020');         // interpolated PV counter on Oct 2
    }

    public function test_meter_columns_are_grouped_by_tariff_in_field_order(): void
    {
        MeterDailyReading::create([
            'date' => '2026-10-01',
            't1_consumed' => 1.111, 't1_fed_in' => 2.222, 't1_balanced_consumed' => 3.333, 't1_balanced_fed_in' => 4.444,
            't2_consumed' => 5.555, 't2_fed_in' => 6.666, 't2_balanced_consumed' => 7.777, 't2_balanced_fed_in' => 8.888,
        ]);

        Livewire::test('pages::readings.index')
            ->assertSeeInOrder(['Inverter', 'Peak (T1)', 'Off-Peak (T2)', 'Measured', 'Balanced', 'Measured', 'Balanced', 'Consumed', 'Fed-in', 'Consumed', 'Fed-in', 'Consumed', 'Fed-in', 'Consumed', 'Fed-in'])
            ->assertSeeInOrder(['1.111', '2.222', '3.333', '4.444', '5.555', '6.666', '7.777', '8.888']);
    }

    public function test_only_actual_pv_readings_are_editable(): void
    {
        PvInverterReading::create(['date' => '2026-10-01', 'value' => 1000]);
        $actual = PvInverterReading::create(['date' => '2026-10-04', 'value' => 1040]);

        $response = Livewire::test('pages::readings.index');

        $response->assertSee(route('pv-inverter.edit', $actual));
        $this->assertSame(2, preg_match_all('~/pv-inverter/\d+/edit~', $response->html()));
    }

    public function test_shows_the_current_month_by_default_with_a_month_dropdown(): void
    {
        PvInverterReading::create(['date' => '2026-08-01', 'value' => 0]);
        PvInverterReading::create(['date' => '2026-10-31', 'value' => 900]);

        Livewire::test('pages::readings.index')
            ->assertSet('month', '2026-10')
            ->assertSeeInOrder(['October 2026', 'August 2026'])   // months with data (+ current)
            ->assertSeeInOrder(['2026-10-31', '2026-10-01'])
            ->assertDontSee('2026-09-30')
            ->set('month', '2026-09')
            ->assertSeeInOrder(['2026-09-30', '2026-09-01'])
            ->assertDontSee('2026-10-01');
    }

    public function test_month_rows_stay_within_the_data_range(): void
    {
        PvInverterReading::create(['date' => '2026-10-03', 'value' => 0]);
        $this->meterDay('2026-10-05', 1);

        Livewire::test('pages::readings.index')
            ->assertSee('2026-10-05')
            ->assertSee('2026-10-03')
            ->assertDontSee('2026-10-06')
            ->assertDontSee('2026-10-02');
    }

    public function test_total_shows_pv_production_of_the_listed_days(): void
    {
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-04', 'value' => 1040]);
        $this->meterDay('2026-10-05', 1);

        // Listed days: Oct 1–5. Counter Oct 5 is extrapolated at 10/day (Sep 30 → Oct 4): 1050 − 1000.
        $this->assertSame(
            ['value' => 50.0, 'exact' => false],
            Livewire::test('pages::readings.index')->instance()->pvProduction,
        );

        PvInverterReading::create(['date' => '2026-10-10', 'value' => 1100]);

        // Oct 1–10: 1100 − 1000 (Sep 30), both actual readings.
        $component = Livewire::test('pages::readings.index');
        $this->assertSame(['value' => 100.0, 'exact' => true], $component->instance()->pvProduction);
        $component->assertSeeInOrder(['Total', '100']);
    }

    public function test_total_pv_production_is_marked_when_interpolated(): void
    {
        PvInverterReading::create(['date' => '2026-09-20', 'value' => 900]);
        PvInverterReading::create(['date' => '2026-10-05', 'value' => 1050]);

        // Oct 1–5: 1050 − interpolated Sep 30 (900 + 150 × 10/15 = 1000)
        Livewire::test('pages::readings.index')
            ->assertSeeInOrder(['Total', '~50']);
    }

    public function test_partial_last_day_counts_only_that_part_of_its_pv(): void
    {
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-05', 'value' => 1050]);
        MeterDailyReading::create(['date' => '2026-10-05', 'hours' => 12, ...array_fill_keys(MeterDailyReading::VALUE_FIELDS, 1)]);

        $component = Livewire::test('pages::readings.index');
        $fraction = MeterDailyReading::first()->pvFraction();

        // Oct 1–5 at 10/day, Oct 5 until noon: an estimate even though both counters are readings.
        $this->assertEqualsWithDelta(40 + 10 * $fraction, $component->instance()->pvProduction['value'], 0.0001);
        $this->assertFalse($component->instance()->pvProduction['exact']);
        $component->assertSeeInOrder(['Total', '~'.number_format(40 + 10 * $fraction)]);
    }

    public function test_incomplete_days_show_their_hours(): void
    {
        MeterDailyReading::create(['date' => '2026-10-04', ...array_fill_keys(MeterDailyReading::VALUE_FIELDS, 1)]);
        MeterDailyReading::create(['date' => '2026-10-05', 'hours' => 6, ...array_fill_keys(MeterDailyReading::VALUE_FIELDS, 1)]);

        Livewire::test('pages::readings.index')
            ->assertSeeInOrder(['2026-10-05', '[6h]', '2026-10-04'])
            ->assertDontSee('[24h]');
    }

    public function test_month_without_data_shows_a_message(): void
    {
        $this->meterDay('2026-08-05', 1);

        Livewire::test('pages::readings.index')
            ->assertSee('No readings in this month.')
            ->assertDontSee('Total');
    }

    public function test_totals_sum_each_meter_column_of_the_month(): void
    {
        $this->meterDay('2026-09-30', 100);          // other month
        MeterDailyReading::create([
            'date' => '2026-10-01',
            't1_consumed' => 1.111, 't1_fed_in' => 2.222, 't1_balanced_consumed' => 3.333, 't1_balanced_fed_in' => 4.444,
            't2_consumed' => 5.555, 't2_fed_in' => 6.666, 't2_balanced_consumed' => 7.777, 't2_balanced_fed_in' => 8.888,
        ]);
        $this->meterDay('2026-10-03', 1);            // day 2 has no meter data

        Livewire::test('pages::readings.index')
            ->assertSeeInOrder(['Total', '2.111', '3.222', '4.333', '5.444', '6.555', '7.666', '8.777', '9.888']);
    }

    public function test_pv_reading_can_be_edited(): void
    {
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        $reading = PvInverterReading::create(['date' => '2026-10-04', 'value' => 1040]);

        Livewire::test('pages::pv-inverter.edit', ['reading' => $reading])
            ->set('date', '2026-10-05')
            ->set('value', 1050)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('readings.index'));

        $reading->refresh();
        $this->assertSame('2026-10-05', $reading->date->toDateString());
        $this->assertSame(1050, $reading->value);
    }

    public function test_edited_reading_must_stay_between_neighbours(): void
    {
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        $reading = PvInverterReading::create(['date' => '2026-10-04', 'value' => 1040]);
        PvInverterReading::create(['date' => '2026-10-10', 'value' => 1100]);

        Livewire::test('pages::pv-inverter.edit', ['reading' => $reading])
            ->set('date', '2026-10-10')
            ->set('value', 999)
            ->call('save')
            ->assertHasErrors(['date', 'value']);

        Livewire::test('pages::pv-inverter.edit', ['reading' => $reading])
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
        $this->assertSame('2026-10-12', array_key_last($values)); // extrapolated past the last reading
        foreach ($values as $date => $value) {
            $this->assertEqualsWithDelta($interpolator->forDate(Carbon::parse($date)), $value, 0.0001, $date);
        }
    }

    private function meterDay(string $date, float $value): void
    {
        MeterDailyReading::create(['date' => $date, ...array_fill_keys(MeterDailyReading::VALUE_FIELDS, $value)]);
    }
}
