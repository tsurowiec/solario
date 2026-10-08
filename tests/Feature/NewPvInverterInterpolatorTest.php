<?php

namespace Tests\Feature;

use App\Models\PvInverterReading;
use App\Services\PvInverterInterpolator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class NewPvInverterInterpolatorTest extends TestCase
{
    use RefreshDatabase;

    private PvInverterInterpolator $interpolator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->interpolator = app(PvInverterInterpolator::class);

        PvInverterReading::create(['date' => '2026-10-01', 'value' => 100]);
        PvInverterReading::create(['date' => '2026-10-11', 'value' => 200]);
        PvInverterReading::create(['date' => '2026-10-15', 'value' => 210]);
    }

    public function test_exact_date_returns_the_reading_value(): void
    {
        $this->assertSame(200.0, $this->interpolator->forDate(Carbon::parse('2026-10-11')));
    }

    public function test_value_is_proportioned_between_surrounding_readings(): void
    {
        $this->assertSame(130.0, $this->interpolator->forDate(Carbon::parse('2026-10-04')));
        $this->assertSame(202.5, $this->interpolator->forDate(Carbon::parse('2026-10-12')));
    }

    public function test_time_of_day_is_ignored(): void
    {
        $this->assertSame(130.0, $this->interpolator->forDate(Carbon::parse('2026-10-04 18:30')));
    }

    public function test_date_before_first_reading_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->interpolator->forDate(Carbon::parse('2026-09-30'));
    }

    public function test_date_after_last_reading_is_extrapolated_at_the_rate_of_the_last_two(): void
    {
        // Oct 11 → Oct 15: 10 over 4 days = 2.5/day.
        $this->assertSame(212.5, $this->interpolator->forDate(Carbon::parse('2026-10-16')));
        $this->assertSame(235.0, $this->interpolator->forDate(Carbon::parse('2026-10-25')));
    }

    public function test_bulk_values_are_extrapolated_after_the_last_reading(): void
    {
        $values = $this->interpolator->between(Carbon::parse('2026-10-14'), Carbon::parse('2026-10-17'));

        $this->assertSame(
            ['2026-10-14' => 207.5, '2026-10-15' => 210.0, '2026-10-16' => 212.5, '2026-10-17' => 215.0],
            $values,
        );
    }

    public function test_date_after_a_single_reading_throws(): void
    {
        PvInverterReading::whereDate('date', '>', '2026-10-01')->delete();

        $this->expectException(InvalidArgumentException::class);

        $this->interpolator->forDate(Carbon::parse('2026-10-02'));
    }

    public function test_bulk_values_stop_after_a_single_reading(): void
    {
        PvInverterReading::whereDate('date', '>', '2026-10-01')->delete();

        $this->assertSame(
            ['2026-10-01' => 100.0],
            $this->interpolator->between(Carbon::parse('2026-09-30'), Carbon::parse('2026-10-03')),
        );
    }
}
