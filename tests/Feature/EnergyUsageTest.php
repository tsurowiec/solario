<?php

namespace Tests\Feature;

use App\Data\EnergySummary;
use App\Models\CarCharge;
use App\Models\MeterDailyReading;
use App\Models\Price;
use App\Models\PvInverterReading;
use App\Models\User;
use App\Services\EnergyUsage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EnergyUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_sums_meter_days_and_pv_counter_difference(): void
    {
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-10', 'value' => 1200]);
        $this->meterDays('2026-10-01', '2026-10-05');

        $d = $this->month('2026-10-15');

        $this->assertSame('2026-10-01', $d->from);
        $this->assertSame('2026-10-05', $d->to);
        // Counter interpolated to 1100 on Oct 5, from 1000 on Sep 30.
        $this->assertEqualsWithDelta(100.0, $d->pvGenerated, 0.0001);
        $this->assertEqualsWithDelta(5 * 3.0, $d->consumed, 0.0001);   // t1 1 + t2 2 per day
        $this->assertEqualsWithDelta(5 * 7.0, $d->fedIn, 0.0001);      // t1 3 + t2 4 per day
        $this->assertEqualsWithDelta(100 - 35, $d->autoConsumed, 0.0001);
        $this->assertEqualsWithDelta(65.0, $d->autoConsumedRatio, 0.0001);
        $this->assertEqualsWithDelta(15 + 65, $d->totalUsage, 0.0001);
        $this->assertSame(5, $d->days);
    }

    public function test_partial_last_day_counts_only_that_part_of_its_pv(): void
    {
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-10', 'value' => 1200]);
        $this->meterDays('2026-10-01', '2026-10-04');
        $this->meterDays('2026-10-05', '2026-10-05', ['hours' => 12]);

        $d = $this->month('2026-10-15');

        $this->assertSame('2026-10-05', $d->to);
        // 20/day: 4 full days (80) and Oct 5 until noon on the daylight curve.
        $fraction = MeterDailyReading::whereDate('date', '2026-10-05')->first()->pvFraction();
        $this->assertGreaterThan(0.3, $fraction);
        $this->assertLessThan(0.5, $fraction);
        $this->assertEqualsWithDelta(80 + 20 * $fraction, $d->pvGenerated, 0.0001);
    }

    public function test_partial_last_day_counts_as_part_of_a_day_in_averages(): void
    {
        Price::create(['since' => '2026-10-01', ...array_fill_keys([...Price::KWH_FIELDS, ...Price::MONTHLY_FIELDS], 0.1)]);
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-10', 'value' => 1200]);
        $this->meterDays('2026-10-01', '2026-10-04');
        $this->meterDays('2026-10-05', '2026-10-05', ['hours' => 12]);
        $pvFraction = MeterDailyReading::whereDate('date', '2026-10-05')->first()->pvFraction();

        $d = $this->month('2026-10-15');

        $this->assertSame(5, $d->days);
        $this->assertSame(4.5, $d->coveredDays);
        $this->assertEqualsWithDelta(4 + $pvFraction, $d->pvDays, 0.0001);
        $this->assertEqualsWithDelta($d->consumed / 4.5, $d->perDay($d->consumed), 0.0001);
        // PV per full day of sun stays at the 20/day rate.
        $this->assertEqualsWithDelta(20.0, $d->perPvDay($d->pvGenerated), 0.0001);
        $this->assertEqualsWithDelta($d->amount / 4.5 * 31, $d->estimatedAmount, 0.0001);

        Livewire::test('month-card', ['month' => '2026-10-01'])
            ->assertSee('20.0 <span', false);   // PV daily average
    }

    public function test_averages_are_zero_when_nothing_is_counted(): void
    {
        $d = new EnergySummary('2026-10-01', '2026-10-01', 0, 0, 0, 0, lastDayHoursFraction: 0, lastDayPvFraction: 0);

        $this->assertSame(0.0, $d->perDay(5));
        $this->assertSame(0.0, $d->perPvDay(5));
    }

    public function test_payable_is_balanced_consumed_minus_80_percent_of_balanced_fed_in(): void
    {
        $d = $this->payable(peak: [5, 2.5], offPeak: [3, 1]);

        $this->assertEqualsWithDelta(2 * (5 - 0.8 * 2.5), $d->peakPayable, 0.0001);      // 6.0
        $this->assertEqualsWithDelta(2 * (3 - 0.8 * 1), $d->offPeakPayable, 0.0001);     // 4.4
        $this->assertSame(0.0, $d->peakSurplus);
        $this->assertSame(0.0, $d->offPeakSurplus);
    }

    public function test_peak_surplus_is_subtracted_from_off_peak(): void
    {
        $d = $this->payable(peak: [1, 5], offPeak: [10, 2]);

        // Peak: 2 × (1 − 4) = −6 → 0, surplus 6; off-peak: 2 × (10 − 1.6) − 6 = 10.8
        $this->assertSame(0.0, $d->peakPayable);
        $this->assertEqualsWithDelta(6.0, $d->peakSurplus, 0.0001);
        $this->assertEqualsWithDelta(10.8, $d->offPeakPayable, 0.0001);
        $this->assertSame(0.0, $d->offPeakSurplus);
    }

    public function test_negative_off_peak_is_shown_as_zero_with_the_surplus(): void
    {
        $d = $this->payable(peak: [1, 5], offPeak: [1, 2]);

        // Off-peak: 2 × (1 − 1.6) − 6 = −7.2 → 0, surplus 7.2
        $this->assertSame(0.0, $d->offPeakPayable);
        $this->assertEqualsWithDelta(7.2, $d->offPeakSurplus, 0.0001);

        Livewire::test('month-card', ['month' => '2026-10-01'])
            ->assertSeeInOrder(['0.0', 'kWh', '(-6.0)', '0.0', 'kWh', '(-7.2)']);
    }

    public function test_payable_percentages_split_the_paid_kwh(): void
    {
        $d = $this->payable(peak: [1, 5], offPeak: [10, 2]);

        // Peak 0, off-peak 10.8 → 0 % / 100 %
        $this->assertSame(0.0, $d->peakPayablePercent);
        $this->assertSame(100.0, $d->offPeakPayablePercent);
    }

    public function test_payable_percentages_when_both_zones_are_paid(): void
    {
        $d = $this->payable(peak: [5, 2.5], offPeak: [3, 1]);

        // Peak 6.0, off-peak 4.4 → 57.7 % / 42.3 %
        $this->assertEqualsWithDelta(6 / 10.4 * 100, $d->peakPayablePercent, 0.0001);
        $this->assertEqualsWithDelta(4.4 / 10.4 * 100, $d->offPeakPayablePercent, 0.0001);

        Livewire::test('month-card', ['month' => '2026-10-01'])
            ->assertSeeInOrder(['57.7', '%', '42.3', '%']);
    }

    public function test_nothing_paid_puts_100_percent_on_off_peak(): void
    {
        $d = $this->payable(peak: [1, 5], offPeak: [1, 2]);

        $this->assertSame(0.0, $d->peakPayablePercent);
        $this->assertSame(100.0, $d->offPeakPayablePercent);
    }

    public function test_amount_and_price_per_kwh(): void
    {
        Price::create([
            'since' => '2026-10-01',
            'peak_sell' => 0.5, 'peak_distr' => 0.3, 'peak_quality' => 0.01, 'peak_oze' => 0.002, 'peak_cogen' => 0.003,
            'off_peak_sell' => 0.4, 'off_peak_distr' => 0.1, 'off_peak_quality' => 0.02, 'off_peak_oze' => 0.004, 'off_peak_cogen' => 0.006,
            'sell_monthly' => 1, 'power_monthly' => 2, 'subscription_monthly' => 3, 'network_monthly' => 4,
        ]);

        // 2 days: raw consumed T1 1/day, T2 2/day; payable peak 6.0, off-peak 4.4 (see payable test)
        $d = $this->payable(peak: [5, 2.5], offPeak: [3, 1]);

        $net = 10 * 2 / 31                          // monthly fees prorated: 2 of 31 days
            + (0.5 + 0.3 + 0.01) * 6.0              // peak sell + distr + quality × payable
            + (0.002 + 0.003) * 2                   // peak oze + cogen × raw consumed
            + (0.4 + 0.1 + 0.02) * 4.4              // off-peak sell + distr + quality × payable
            + (0.004 + 0.006) * 4;                  // off-peak oze + cogen × raw consumed

        $this->assertEqualsWithDelta($net * 1.23, $d->amount, 0.000001);
        $this->assertEqualsWithDelta($d->amount / $d->totalUsage, $d->pricePerUnit, 0.000001);

        Livewire::test('month-card', ['month' => '2026-10-01'])
            ->assertSee(number_format($d->amount, 2))
            ->assertSee('PLN/kWh');
    }

    public function test_cars_and_household_split_usage_and_amount(): void
    {
        Price::create(['since' => '2026-10-01', ...array_fill_keys([...Price::KWH_FIELDS, ...Price::MONTHLY_FIELDS], 0.1)]);
        CarCharge::create(['date' => '2026-10-01', 'car_id' => 'tesia', 'charged' => 10]);
        CarCharge::create(['date' => '2026-10-02', 'car_id' => 'tesia', 'charged' => 5]);
        CarCharge::create(['date' => '2026-10-03', 'car_id' => 'tesia', 'charged' => 99]);  // outside the period (1–2 Oct)
        CarCharge::create(['date' => '2026-09-30', 'car_id' => 'tessy', 'charged' => 99]);  // outside the period

        $d = $this->payable(peak: [5, 2.5], offPeak: [3, 1]);

        $this->assertSame(['tesia' => 15.0, 'tessy' => 0.0], $d->carUsage);
        $this->assertEqualsWithDelta(15 * $d->pricePerUnit, $d->carAmounts['tesia'], 0.000001);
        $this->assertSame(0.0, $d->carAmounts['tessy']);
        $this->assertEqualsWithDelta($d->totalUsage - 15, $d->householdUsage, 0.000001);
        $this->assertEqualsWithDelta($d->amount - $d->carAmounts['tesia'], $d->householdAmount, 0.000001);

        Livewire::test('month-card', ['month' => '2026-10-01'])
            ->assertSee(number_format($d->carAmounts['tesia'], 2))
            ->assertSee(number_format($d->householdAmount, 2));
    }

    public function test_without_prices_cars_and_household_show_only_kwh(): void
    {
        CarCharge::create(['date' => '2026-10-01', 'car_id' => 'tessy', 'charged' => 7]);

        $d = $this->payable(peak: [5, 2.5], offPeak: [3, 1]);

        $this->assertSame(['tesia' => 0.0, 'tessy' => 7.0], $d->carUsage);
        $this->assertSame(['tesia' => null, 'tessy' => null], $d->carAmounts);
        $this->assertNull($d->householdAmount);
        $this->assertEqualsWithDelta($d->totalUsage - 7, $d->householdUsage, 0.000001);
    }

    public function test_partial_month_shows_an_estimate_for_the_whole_month(): void
    {
        Price::create(['since' => '2026-10-01', ...array_fill_keys([...Price::KWH_FIELDS, ...Price::MONTHLY_FIELDS], 0.1)]);

        $d = $this->payable(peak: [5, 2.5], offPeak: [3, 1]);   // 2 of 31 days

        $this->assertEqualsWithDelta($d->amount / 2 * 31, $d->estimatedAmount, 0.000001);

        Livewire::test('month-card', ['month' => '2026-10-01'])
            ->assertSee('(~'.number_format($d->estimatedAmount, 2).')');
    }

    public function test_full_month_has_no_estimate(): void
    {
        Price::create(['since' => '2026-09-01', ...array_fill_keys([...Price::KWH_FIELDS, ...Price::MONTHLY_FIELDS], 0.1)]);
        PvInverterReading::create(['date' => '2026-08-31', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1300]);
        $this->meterDays('2026-09-01', '2026-09-30');

        $d = $this->month('2026-09-01');

        $this->assertSame(30, $d->days);
        $this->assertNotNull($d->amount);
        $this->assertNull($d->estimatedAmount);
    }

    public function test_prices_of_the_month_are_used(): void
    {
        Price::create(['since' => '2026-09-01', ...array_fill_keys([...Price::KWH_FIELDS, ...Price::MONTHLY_FIELDS], 1)]);
        Price::create(['since' => '2026-10-01', ...array_fill_keys([...Price::KWH_FIELDS, ...Price::MONTHLY_FIELDS], 0)]);

        $this->assertSame(0.0, $this->payable(peak: [5, 2.5], offPeak: [3, 1])->amount);
    }

    public function test_no_prices_means_no_amount(): void
    {
        $d = $this->payable(peak: [5, 2.5], offPeak: [3, 1]);

        $this->assertNull($d->amount);
        $this->assertNull($d->pricePerUnit);

        Livewire::test('month-card', ['month' => '2026-10-01'])->assertDontSee('PLN/kWh');
    }

    /**
     * Two days of meter data with the given balanced [consumed, fed-in] per day.
     */
    private function payable(array $peak, array $offPeak)
    {
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-10', 'value' => 1200]);
        $this->meterDays('2026-10-01', '2026-10-02', [
            't1_balanced_consumed' => $peak[0], 't1_balanced_fed_in' => $peak[1],
            't2_balanced_consumed' => $offPeak[0], 't2_balanced_fed_in' => $offPeak[1],
        ]);

        return $this->month('2026-10-01');
    }

    public function test_pv_is_extrapolated_past_the_last_reading(): void
    {
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-03', 'value' => 1030]);
        $this->meterDays('2026-10-01', '2026-10-05');

        $d = $this->month('2026-10-01');

        // 10/day from the last two readings → Oct 5 at 1050.
        $this->assertSame('2026-10-05', $d->to);
        $this->assertEqualsWithDelta(50.0, $d->pvGenerated, 0.0001);
        $this->assertEqualsWithDelta(5 * 3.0, $d->consumed, 0.0001);
    }

    public function test_period_ends_at_a_single_pv_reading(): void
    {
        PvInverterReading::create(['date' => '2026-10-03', 'value' => 1030]);
        $this->meterDays('2026-10-01', '2026-10-05');

        $this->assertNull($this->month('2026-10-01'));
    }

    public function test_period_starts_when_pv_data_covers_the_previous_day(): void
    {
        PvInverterReading::create(['date' => '2026-10-02', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-05', 'value' => 1030]);
        $this->meterDays('2026-10-01', '2026-10-05');

        $d = $this->month('2026-10-01');

        $this->assertSame('2026-10-03', $d->from);
        $this->assertSame('2026-10-05', $d->to);
        $this->assertEqualsWithDelta(30.0, $d->pvGenerated, 0.0001);
    }

    public function test_period_stops_at_the_first_missing_meter_day(): void
    {
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-31', 'value' => 1310]);
        $this->meterDays('2026-10-01', '2026-10-03');
        $this->meterDays('2026-10-05', '2026-10-06');

        $this->assertSame('2026-10-03', $this->month('2026-10-01')->to);
    }

    public function test_month_without_full_data_returns_null(): void
    {
        $this->meterDays('2026-10-01', '2026-10-05');

        $this->assertNull($this->month('2026-10-01'));
    }

    public function test_dashboard_shows_the_month_card(): void
    {
        $this->actingAs(User::factory()->create());
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-10', 'value' => 1200]);
        $this->meterDays('2026-10-01', '2026-10-05');

        $this->get(route('dashboard'))->assertOk()->assertSee('October 2026');

        Livewire::test('month-card', ['month' => '2026-10-01'])
            ->assertSee('October 2026')
            ->assertSee('1–5 Oct')
            ->assertSee('100 <span', false)       // PV total
            ->assertSee('20.0 <span', false);     // PV daily average: 100 kWh / 5 days
    }

    public function test_dashboard_shows_meter_month_when_pv_readings_end_before_it(): void
    {
        $this->actingAs(User::factory()->create());
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        $this->meterDays('2026-10-01', '2026-10-05');

        $this->get(route('dashboard'))->assertSee('October 2026')->assertDontSee('September 2026');
    }

    public function test_dashboard_shows_meter_month_when_pv_is_extrapolated(): void
    {
        $this->actingAs(User::factory()->create());
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-31', 'value' => 1310]);
        $this->meterDays('2026-10-01', '2026-11-02');

        $this->get(route('dashboard'))->assertSee('November 2026');
        $this->assertSame('2026-11-02', $this->month('2026-11-01')->to);
    }

    private function month(string $date)
    {
        return app(EnergyUsage::class)->month(Carbon::parse($date));
    }

    private function meterDays(string $from, string $to, array $values = []): void
    {
        for ($day = Carbon::parse($from); $day->lte(Carbon::parse($to)); $day->addDay()) {
            MeterDailyReading::create([
                'date' => $day->toDateString(),
                't1_consumed' => 1, 't2_consumed' => 2,
                't1_fed_in' => 3, 't2_fed_in' => 4,
                't1_balanced_consumed' => 0, 't2_balanced_consumed' => 0,
                't1_balanced_fed_in' => 0, 't2_balanced_fed_in' => 0,
                ...$values,
            ]);
        }
    }
}
