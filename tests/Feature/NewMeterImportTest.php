<?php

namespace Tests\Feature;

use App\Models\MeterDailyReading;
use App\Models\User;
use App\Services\MeterCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class NewMeterImportTest extends TestCase
{
    use RefreshDatabase;

    private const TYPES = [
        'pobór [kWh]',
        'oddanie [kWh]',
        'pobrana po zbilansowaniu [kWh]',
        'oddana po zbilansowaniu [kWh]',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        auth()->logout();

        $this->get(route('new.meter-import.create'))->assertRedirect(route('login'));
    }

    public function test_dashboard_links_to_the_import(): void
    {
        $this->get(route('new.dashboard'))->assertSee(route('new.meter-import.create'));
    }

    public function test_hourly_rows_are_summed_per_day_zone_and_type(): void
    {
        $result = $this->importer()->importString($this->csv(['2026-10-01']));

        $this->assertSame(['2026-10-01'], $result->imported);

        $day = MeterDailyReading::first();
        // Hours 7–22 are T1 (16 h), the remaining 8 h are T2; each type has its own per-hour value.
        $this->assertSame(16 * 0.101, $day->t1_consumed);
        $this->assertSame(16 * 0.202, $day->t1_fed_in);
        $this->assertSame(16 * 0.303, $day->t1_balanced_consumed);
        $this->assertSame(16 * 0.404, $day->t1_balanced_fed_in);
        $this->assertSame(8 * 0.101, $day->t2_consumed);
        $this->assertSame(8 * 0.202, $day->t2_fed_in);
        $this->assertSame(8 * 0.303, $day->t2_balanced_consumed);
        $this->assertSame(8 * 0.404, $day->t2_balanced_fed_in);
    }

    public function test_hour_24_belongs_to_the_same_day(): void
    {
        $this->importer()->importString($this->csv(['2026-10-01', '2026-10-02']));

        $this->assertSame(2, MeterDailyReading::count());
        $this->assertSame(['2026-10-01', '2026-10-02'], MeterDailyReading::orderBy('date')->get()->map->date->map->toDateString()->all());
    }

    public function test_incomplete_days_are_skipped(): void
    {
        $csv = $this->csv(['2026-10-01', '2026-10-02'], skipHour: ['2026-10-02', 13]);

        $result = $this->importer()->importString($csv);

        $this->assertSame(['2026-10-01'], $result->imported);
        $this->assertSame(['2026-10-02'], $result->skipped);
        $this->assertDatabaseCount('meter_daily_readings', 1);
    }

    public function test_dst_day_with_25_hours_is_complete(): void
    {
        $result = $this->importer()->importString($this->csv(['2026-10-25'], hours: 25));

        $this->assertSame(['2026-10-25'], $result->imported);
    }

    public function test_dst_day_with_24_hours_is_incomplete(): void
    {
        $result = $this->importer()->importString($this->csv(['2026-10-25']));

        $this->assertSame(['2026-10-25'], $result->skipped);
    }

    public function test_existing_days_are_overwritten(): void
    {
        MeterDailyReading::create(['date' => '2026-10-01', ...array_fill_keys(MeterDailyReading::VALUE_FIELDS, 999)]);

        $this->importer()->importString($this->csv(['2026-10-01']));

        $this->assertDatabaseCount('meter_daily_readings', 1);
        $this->assertSame(16 * 0.101, MeterDailyReading::first()->t1_consumed);
    }

    public function test_only_licznik_rows_are_used(): void
    {
        $csv = $this->csv(['2026-10-01'])."2026-10-01 8:00;T1;5,000;pobór [kWh]; szacowany; \n";

        $this->importer()->importString($csv);

        $this->assertSame(16 * 0.101, MeterDailyReading::first()->t1_consumed);
    }

    public function test_file_is_uploaded_and_imported(): void
    {
        $file = UploadedFile::fake()->createWithContent('data.csv', $this->csv(['2026-10-01']));

        Livewire::test('pages::new.meter-import.create')
            ->set('file', $file)
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('imported', ['2026-10-01'])
            ->assertSee('1 day imported');

        $this->assertDatabaseCount('meter_daily_readings', 1);
    }

    private function importer(): MeterCsvImporter
    {
        return app(MeterCsvImporter::class);
    }

    /**
     * Builds a CSV in the meter export format. Hours 7–22 are T1, the rest T2.
     *
     * @param  list<string>  $dates
     * @param  array{0: string, 1: int}|null  $skipHour  [date, hour] to leave out for every type
     */
    private function csv(array $dates, int $hours = 24, ?array $skipHour = null): string
    {
        $lines = ['Data; Strefa; Wartość ;Rodzaj;Status;'];

        foreach (self::TYPES as $i => $type) {
            $n = $i + 1;
            $value = "0,{$n}0{$n}";

            foreach ($dates as $date) {
                for ($hour = 1; $hour <= $hours; $hour++) {
                    if ($skipHour === [$date, $hour]) {
                        continue;
                    }

                    $zone = $hour >= 7 && $hour <= 22 ? 'T1' : 'T2';
                    $lines[] = "{$date} {$hour}:00;{$zone};{$value};{$type}; licznik; ";
                }
            }
        }

        return implode("\n", $lines)."\n";
    }
}
