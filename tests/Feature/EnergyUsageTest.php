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
        Price::create(['since' => '2026-10-01', ...$this->twoZonePrices(0.1)]);
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
            ->assertSee('>20.0</span>', false);   // PV daily average
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
        $this->assertEqualsWithDelta(4.0, $d->flows['peak']->fromOwn, 0.0001);
        $this->assertSame(0.0, $d->flows['off_peak']->fromOtherZones);
        $this->assertSame(0.0, $d->creditOut);
    }

    public function test_peak_surplus_goes_to_off_peak(): void
    {
        $d = $this->payable(peak: [1, 5], offPeak: [10, 2]);

        // Peak: own credit 8 covers 2, surplus 6; off-peak: 20 − 3.2 − 6 = 10.8
        $this->assertSame(0.0, $d->peakPayable);
        $this->assertEqualsWithDelta(2.0, $d->flows['peak']->fromOwn, 0.0001);
        $this->assertEqualsWithDelta(6.0, $d->flows['off_peak']->fromOtherZones, 0.0001);
        $this->assertEqualsWithDelta(10.8, $d->offPeakPayable, 0.0001);
        $this->assertSame(0.0, $d->creditOut);
    }

    public function test_off_peak_surplus_goes_to_peak(): void
    {
        $d = $this->payable(peak: [10, 0], offPeak: [1, 5]);

        // Off-peak: own credit 8 covers 2, surplus 6; peak: 20 − 6 = 14
        $this->assertSame(0.0, $d->offPeakPayable);
        $this->assertEqualsWithDelta(6.0, $d->flows['peak']->fromOtherZones, 0.0001);
        $this->assertEqualsWithDelta(14.0, $d->peakPayable, 0.0001);
    }

    public function test_credit_left_carries_to_the_next_month_and_is_applied_first(): void
    {
        $october = $this->payable(peak: [1, 5], offPeak: [1, 2]);

        // Surplus: peak 8 − 2 = 6, off-peak 3.2 − 2 = 1.2
        $this->assertSame(0.0, $october->peakPayable + $october->offPeakPayable);
        $this->assertEqualsWithDelta(7.2, $october->creditOut, 0.0001);

        $this->meterDays('2026-11-01', '2026-11-01', ['t1_balanced_consumed' => 5, 't1_balanced_fed_in' => 1, 't2_balanced_consumed' => 4]);
        $november = $this->month('2026-11-01');

        // Carried 7.2 first: peak 5 → 0, off-peak 4 − 2.2 = 1.8; peak's own credit 0.8 is then surplus → off-peak 1.0
        $this->assertEqualsWithDelta(7.2, $november->creditIn, 0.0001);
        $this->assertEqualsWithDelta(5.0, $november->flows['peak']->fromPreviousMonth, 0.0001);
        $this->assertEqualsWithDelta(2.2, $november->flows['off_peak']->fromPreviousMonth, 0.0001);
        $this->assertSame(0.0, $november->flows['peak']->fromOwn);
        $this->assertEqualsWithDelta(0.8, $november->flows['off_peak']->fromOtherZones, 0.0001);
        $this->assertEqualsWithDelta(1.0, $november->offPeakPayable, 0.0001);
        $this->assertSame(0.0, $november->creditOut);
    }

    public function test_credit_passes_through_a_month_without_data(): void
    {
        $this->payable(peak: [1, 5], offPeak: [1, 2]);
        $this->meterDays('2026-12-01', '2026-12-01', ['t1_balanced_consumed' => 10]);

        $this->assertNull($this->month('2026-11-01'));
        $this->assertEqualsWithDelta(7.2, $this->month('2026-12-01')->creditIn, 0.0001);
    }

    public function test_three_zones_settle_most_expensive_first(): void
    {
        // The Flows card example: peak (sunset), early-peak (sunrise), off-peak (moon).
        $d = new EnergySummary('2026-10-01', '2026-10-31', 0, 0, 0, 0,
            peakBalancedConsumed: 30, peakBalancedFedIn: 10,
            offPeakBalancedConsumed: 400, offPeakBalancedFedIn: 200,
            earlyPeakBalancedConsumed: 30, earlyPeakBalancedFedIn: 200,
            creditIn: 5,
        );

        $flow = fn (string $zone) => array_map(fn ($v) => is_float($v) ? round($v, 4) : $v, (array) $d->flows[$zone]);

        $this->assertEquals(['zone' => 'peak', 'fedIn' => 10.0, 'consumed' => 30.0, 'fromPreviousMonth' => 5.0, 'fromOwn' => 8.0, 'fromOtherZones' => 17.0, 'toPool' => 0.0, 'payable' => 0.0], $flow('peak'));
        $this->assertEquals(['zone' => 'early_peak', 'fedIn' => 200.0, 'consumed' => 30.0, 'fromPreviousMonth' => 0.0, 'fromOwn' => 30.0, 'fromOtherZones' => 0.0, 'toPool' => 130.0, 'payable' => 0.0], $flow('early_peak'));
        $this->assertEquals(['zone' => 'off_peak', 'fedIn' => 200.0, 'consumed' => 400.0, 'fromPreviousMonth' => 0.0, 'fromOwn' => 160.0, 'fromOtherZones' => 113.0, 'toPool' => 0.0, 'payable' => 127.0], $flow('off_peak'));
        $this->assertEqualsWithDelta(0.0, $d->creditOut, 0.0001);
    }

    public function test_carried_credit_left_joins_the_surplus(): void
    {
        // Credit 10 → peak 30 − 10; own credit 4 / 40 / 0; early-peak surplus 32 → peak 16 → 0, off-peak 50 − 16 = 34
        $d = new EnergySummary('2026-10-01', '2026-10-31', 0, 0, 0, 0,
            peakBalancedConsumed: 30, peakBalancedFedIn: 5,
            offPeakBalancedConsumed: 50,
            earlyPeakBalancedConsumed: 8, earlyPeakBalancedFedIn: 50,
            creditIn: 10,
        );

        $this->assertSame(0.0, $d->peakPayable);
        $this->assertSame(0.0, $d->earlyPeakPayable);
        $this->assertEqualsWithDelta(34.0, $d->offPeakPayable, 0.0001);
        $this->assertSame(0.0, $d->creditOut);
    }

    public function test_early_peak_prices_switch_meter_zones_to_three(): void
    {
        Price::create(['since' => '2026-10-01', ...$this->twoZonePrices(0), 'early_peak_sell' => 1]);
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-10', 'value' => 1200]);
        $this->meterDays('2026-10-01', '2026-10-01', [
            't1_consumed' => 10, 't2_consumed' => 20, 't3_consumed' => 30,
            't1_balanced_consumed' => 10, 't2_balanced_consumed' => 20, 't3_balanced_consumed' => 30,
        ]);

        $d = $this->month('2026-10-01');

        // T1 morning = early-peak, T2 evening = peak, T3 = off-peak.
        $this->assertTrue($d->hasEarlyPeak);
        $this->assertEqualsWithDelta(10.0, $d->earlyPeakConsumed, 0.0001);
        $this->assertEqualsWithDelta(20.0, $d->peakConsumed, 0.0001);
        $this->assertEqualsWithDelta(30.0, $d->offPeakConsumed, 0.0001);
        $this->assertEqualsWithDelta(60.0, $d->consumed, 0.0001);
        $this->assertEqualsWithDelta(10.0, $d->earlyPeakPayable, 0.0001);
        // Only early-peak sell is priced: 10 kWh × 1 PLN × VAT.
        $this->assertSame(12.3, $d->amount);
    }

    public function test_amount_and_price_per_kwh(): void
    {
        Price::create([
            'since' => '2026-10-01',
            'peak_sell' => 0.5, 'peak_distr' => 0.3, 'peak_quality' => 0.01, 'peak_oze' => 0.002, 'peak_cogen' => 0.003,
            'off_peak_sell' => 0.4, 'off_peak_distr' => 0.1, 'off_peak_quality' => 0.02, 'off_peak_oze' => 0.004, 'off_peak_cogen' => 0.006,
            'sell_monthly' => 1, 'power_monthly' => 2, 'subscription_monthly' => 3, 'network_monthly' => 4,
        ]);

        // 2 days: balanced consumed T1 10, T2 6; payable peak 6.0, off-peak 4.4 → 4 (see payable test)
        $d = $this->payable(peak: [5, 2.5], offPeak: [3, 1]);

        // Gross rows, each from a net rounded to 0.01 PLN.
        $gross = 0.07 + 0.16 + 0.23 + 0.32         // monthly fees prorated: 2 of 31 days
            + 3.69 + 2.21 + 0.07                    // peak sell, distr, quality × 6
            + 0.02 + 0.04                           // peak oze, cogen × 10
            + 1.97 + 0.49 + 0.10                    // off-peak sell, distr, quality × 4
            + 0.02 + 0.05;                          // off-peak oze, cogen × 6

        $this->assertEqualsWithDelta($gross, $d->amount, 0.000001);
        $this->assertEqualsWithDelta($d->amount / $d->totalUsage, $d->pricePerUnit, 0.000001);

        Livewire::test('month-card', ['month' => '2026-10-01'])
            ->assertSee(number_format($d->amount, 2))
            ->assertSee('PLN/kWh');
    }

    public function test_amount_matches_the_october_2026_invoice(): void
    {
        $price = new Price([
            'peak_sell' => 0.642, 'peak_distr' => 0.3298, 'peak_quality' => 0.0332, 'peak_oze' => 0.0073, 'peak_cogen' => 0.003,
            'off_peak_sell' => 0.426, 'off_peak_distr' => 0.0512, 'off_peak_quality' => 0.0332, 'off_peak_oze' => 0.0073, 'off_peak_cogen' => 0.003,
            'sell_monthly' => 8.5, 'power_monthly' => 24.05, 'subscription_monthly' => 0, 'network_monthly' => 10.86,
        ]);

        $d = new EnergySummary(
            from: '2026-10-01',
            to: '2026-10-31',
            pvGenerated: 775,
            peakConsumed: 204.525,
            offPeakConsumed: 704.167,
            fedIn: 536.838,
            peakBalancedConsumed: 163.086,
            peakBalancedFedIn: 258.422,
            offPeakBalancedConsumed: 589.434,
            offPeakBalancedFedIn: 122.244,
            price: $price,
        );

        $this->assertSame(344.19, $d->amount);
    }

    public function test_cars_and_household_split_usage_and_amount(): void
    {
        Price::create(['since' => '2026-10-01', ...$this->twoZonePrices(0.1)]);
        CarCharge::create(['date' => '2026-10-01', 'car_id' => 'tesia', 'charged' => 10]);
        CarCharge::create(['date' => '2026-10-02', 'car_id' => 'tesia', 'charged' => 5]);
        CarCharge::create(['date' => '2026-10-03', 'car_id' => 'tesia', 'charged' => 99]);  // outside the period (1–2 Oct)
        CarCharge::create(['date' => '2026-09-30', 'car_id' => 'tessy', 'charged' => 99]);  // outside the period

        $d = $this->payable(peak: [5, 2.5], offPeak: [3, 1]);

        $this->assertEqualsWithDelta(['tesia' => 15 * CarCharge::GRID_RATIO, 'tessy' => 0.0], $d->carUsage, 0.000001);  // taken from the grid
        $this->assertEqualsWithDelta(15 * CarCharge::GRID_RATIO * $d->pricePerUnit, $d->carAmounts['tesia'], 0.000001);
        $this->assertSame(0.0, $d->carAmounts['tessy']);
        $this->assertEqualsWithDelta(['tesia' => 75.0, 'tessy' => 0.0], $d->carRange, 0.000001);  // 15 kWh charged at 20 kWh / 100 km
        $this->assertEqualsWithDelta($d->carAmounts['tesia'] / 75 * 100, $d->carCostPer100Km['tesia'], 0.000001);
        $this->assertNull($d->carCostPer100Km['tessy']);  // no range
        $this->assertEqualsWithDelta($d->totalUsage - 15 * CarCharge::GRID_RATIO, $d->householdUsage, 0.000001);
        $this->assertEqualsWithDelta($d->amount - $d->carAmounts['tesia'], $d->householdAmount, 0.000001);

        Livewire::test('month-card', ['month' => '2026-10-01'])
            ->assertSee(number_format($d->carAmounts['tesia'], 2))
            ->assertSeeInOrder(['75', 'km', number_format($d->carCostPer100Km['tesia'], 2), 'PLN/100km'])  // averages view
            ->assertSee(number_format($d->householdAmount, 2));
    }

    public function test_without_prices_cars_and_household_show_only_kwh(): void
    {
        CarCharge::create(['date' => '2026-10-01', 'car_id' => 'tessy', 'charged' => 7]);

        $d = $this->payable(peak: [5, 2.5], offPeak: [3, 1]);

        $this->assertEqualsWithDelta(['tesia' => 0.0, 'tessy' => 7 * CarCharge::GRID_RATIO], $d->carUsage, 0.000001);
        $this->assertSame(['tesia' => null, 'tessy' => null], $d->carAmounts);
        $this->assertEqualsWithDelta(['tesia' => 0.0, 'tessy' => 7 / 15 * 100], $d->carRange, 0.000001);  // range doesn't need prices

        Livewire::test('month-card', ['month' => '2026-10-01'])
            ->assertSeeInOrder(['47', 'km'])
            ->assertDontSee('/100km');
        $this->assertNull($d->householdAmount);
        $this->assertEqualsWithDelta($d->totalUsage - 7 * CarCharge::GRID_RATIO, $d->householdUsage, 0.000001);
    }

    public function test_partial_month_shows_an_estimate_for_the_whole_month(): void
    {
        Price::create(['since' => '2026-10-01', ...$this->twoZonePrices(0.1)]);

        $d = $this->payable(peak: [5, 2.5], offPeak: [3, 1]);   // 2 of 31 days

        $this->assertEqualsWithDelta($d->amount / 2 * 31, $d->estimatedAmount, 0.000001);
        $this->assertEqualsWithDelta($d->totalUsage / 2 * 31, $d->estimatedUsage, 0.000001);

        // The estimates get their own row below the amount, not a note next to it.
        Livewire::test('month-card', ['month' => '2026-10-01'])
            ->assertDontSee('(~')
            ->assertSeeInOrder([
                number_format($d->amount, 2), 'PLN', number_format($d->pricePerUnit, 2), 'PLN/kWh',
                '~'.number_format($d->estimatedAmount, 2), 'PLN', '~'.number_format($d->estimatedUsage), 'kWh',
            ]);
    }

    public function test_full_month_has_no_estimate(): void
    {
        Price::create(['since' => '2026-09-01', ...$this->twoZonePrices(0.1)]);
        PvInverterReading::create(['date' => '2026-08-31', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1300]);
        $this->meterDays('2026-09-01', '2026-09-30');

        $d = $this->month('2026-09-01');

        $this->assertSame(30, $d->days);
        $this->assertNotNull($d->amount);
        $this->assertNull($d->estimatedAmount);
        $this->assertNull($d->estimatedUsage);

        Livewire::test('month-card', ['month' => '2026-09-01'])->assertDontSee('Estimated for the whole month');
    }

    public function test_prices_of_the_month_are_used(): void
    {
        Price::create(['since' => '2026-09-01', ...$this->twoZonePrices(1)]);
        Price::create(['since' => '2026-10-01', ...$this->twoZonePrices(0)]);

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
            ->assertSee('>100</span>', false)       // PV total
            ->assertSee('>20.0</span>', false);     // PV daily average: 100 kWh / 5 days
    }

    public function test_dashboard_shows_the_flows_card_first(): void
    {
        $this->actingAs(User::factory()->create());
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-31', 'value' => 1310]);
        $this->meterDays('2026-10-01', '2026-11-02');

        $this->get(route('dashboard'))
            ->assertSeeInOrder(['November 2026 - Flows', 'November 2026', 'October 2026'])
            ->assertDontSee('October 2026 - Flows');
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

    public function test_t3_counts_as_off_peak(): void
    {
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-31', 'value' => 1310]);
        $this->meterDays('2026-10-01', '2026-10-01', [
            't2_balanced_consumed' => 2, 't2_balanced_fed_in' => 1,
            't3_consumed' => 5, 't3_fed_in' => 6,
            't3_balanced_consumed' => 3, 't3_balanced_fed_in' => 1,
        ]);

        $d = $this->month('2026-10-01');

        $this->assertEqualsWithDelta(7.0, $d->offPeakConsumed, 0.0001);   // T2 2 + T3 5
        $this->assertEqualsWithDelta(13.0, $d->fedIn, 0.0001);            // T1 3 + T2 4 + T3 6
        $this->assertEqualsWithDelta(5.0, $d->offPeakBalancedConsumed, 0.0001);
        $this->assertEqualsWithDelta(2.0, $d->offPeakBalancedFedIn, 0.0001);
        $this->assertEqualsWithDelta(3.4, $d->offPeakPayable, 0.0001);    // 5 − 0.8 × 2
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
