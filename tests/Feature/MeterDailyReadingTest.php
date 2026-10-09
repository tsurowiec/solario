<?php

namespace Tests\Feature;

use App\Models\MeterDailyReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeterDailyReadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_created_without_hours_covers_the_whole_day(): void
    {
        $this->assertSame(24, $this->reading('2026-10-01')->hours);
        $this->assertSame(25, $this->reading('2026-10-25')->hours);
        $this->assertSame(23, $this->reading('2026-03-29')->hours);
    }

    public function test_day_fraction_follows_the_daylight_curve(): void
    {
        config(['services.pv.latitude' => 50.06, 'services.pv.longitude' => 19.94]);

        // Kraków, early October: sunrise ~6:50, sunset ~18:20, solar noon ~12:35 (CEST).
        $this->assertSame(0.0, $this->reading('2026-10-02', 6)->dayFraction());
        $this->assertEqualsWithDelta(0.09, $this->reading('2026-10-03', 9)->dayFraction(), 0.01);
        $this->assertEqualsWithDelta(0.43, $this->reading('2026-10-04', 12)->dayFraction(), 0.01);
        $this->assertEqualsWithDelta(1.0, $this->reading('2026-10-05', 18)->dayFraction(), 0.01);
        $this->assertSame(1.0, $this->reading('2026-10-06')->dayFraction());
    }

    public function test_day_fraction_counts_real_hours_on_dst_days(): void
    {
        config(['services.pv.latitude' => 50.06, 'services.pv.longitude' => 19.94]);

        // Clocks go back at 3:00, so 13 hours after midnight is 12:00 CET, past solar noon (~11:40 CET).
        $this->assertEqualsWithDelta(0.59, $this->reading('2026-10-25', 13)->dayFraction(), 0.01);
        $this->assertSame(1.0, $this->reading('2026-10-26', 25)->dayFraction());
    }

    private function reading(string $date, ?int $hours = null): MeterDailyReading
    {
        return MeterDailyReading::create([
            'date' => $date,
            ...($hours === null ? [] : ['hours' => $hours]),
            ...array_fill_keys(MeterDailyReading::VALUE_FIELDS, 0),
        ]);
    }
}
