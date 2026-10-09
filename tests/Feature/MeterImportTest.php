<?php

namespace Tests\Feature;

use App\Models\MeterDailyReading;
use App\Models\User;
use App\Services\MeterCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class MeterImportTest extends TestCase
{
    use RefreshDatabase;

    private const TYPES = [
        'pobór [kWh]',
        'oddanie [kWh]',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        auth()->logout();

        $this->get(route('meter-import.create'))->assertRedirect(route('login'));
    }

    public function test_readings_page_links_to_the_import(): void
    {
        $this->get(route('readings.index'))->assertSee(route('meter-import.create'));
    }

    public function test_hourly_rows_are_summed_per_day_zone_and_type(): void
    {
        $result = $this->importer()->importString($this->csv(['2026-10-01']));

        $this->assertSame(['2026-10-01'], $result->imported);

        $day = MeterDailyReading::first();
        // Hours 7–22 are T1 (16 h), the remaining 8 h are T2; each type has its own per-hour value.
        $this->assertSame(16 * 0.101, $day->t1_consumed);
        $this->assertSame(16 * 0.202, $day->t1_fed_in);
        $this->assertSame(8 * 0.101, $day->t2_consumed);
        $this->assertSame(8 * 0.202, $day->t2_fed_in);
    }

    public function test_balanced_values_are_netted_per_hour(): void
    {
        // Every hour consumes 0.300; fed-in is 1.000 in hours 10–14 (T1) and 0.100 otherwise.
        $csv = $this->csv(['2026-10-01'], value: fn (string $type, int $hour) => match (true) {
            $type === 'pobór [kWh]' => '0,300',
            $hour >= 10 && $hour <= 14 => '1,000',
            default => '0,100',
        });

        $this->importer()->importString($csv);

        $day = MeterDailyReading::first();
        // T1: 11 hours net 0.200 consumed, 5 hours net 0.700 fed-in — not netted across the day.
        $this->assertSame(11 * 0.2, $day->t1_balanced_consumed);
        $this->assertSame(5 * 0.7, $day->t1_balanced_fed_in);
        $this->assertSame(8 * 0.2, $day->t2_balanced_consumed);
        $this->assertSame(0.0, $day->t2_balanced_fed_in);
    }

    public function test_balanced_rows_in_the_csv_are_ignored(): void
    {
        $csv = $this->csv(['2026-10-01']);
        for ($hour = 1; $hour <= 24; $hour++) {
            $zone = $hour >= 7 && $hour <= 22 ? 'T1' : 'T2';
            $csv .= "2026-10-01 {$hour}:00;{$zone};9,000;pobrana po zbilansowaniu [kWh]; licznik; \n";
            $csv .= "2026-10-01 {$hour}:00;{$zone};9,000;oddana po zbilansowaniu [kWh]; licznik; \n";
        }

        $this->importer()->importString($csv);

        $day = MeterDailyReading::first();
        $this->assertSame(0.0, $day->t1_balanced_consumed);
        $this->assertSame(16 * 0.101, $day->t1_balanced_fed_in);
    }

    public function test_hour_24_belongs_to_the_same_day(): void
    {
        $this->importer()->importString($this->csv(['2026-10-01', '2026-10-02']));

        $this->assertSame(2, MeterDailyReading::count());
        $this->assertSame(['2026-10-01', '2026-10-02'], MeterDailyReading::orderBy('date')->get()->map->date->map->toDateString()->all());
    }

    public function test_incomplete_days_are_saved_with_their_hours(): void
    {
        $csv = $this->csv(['2026-10-01', '2026-10-02'], skipHour: ['2026-10-02', 13]);

        $result = $this->importer()->importString($csv);

        $this->assertSame(['2026-10-01', '2026-10-02'], $result->imported);
        $this->assertSame(['2026-10-02'], $result->incomplete);
        $this->assertSame([], $result->skipped);
        $this->assertSame(24, MeterDailyReading::whereDate('date', '2026-10-01')->value('hours'));
        $this->assertSame(23, MeterDailyReading::whereDate('date', '2026-10-02')->value('hours'));
    }

    public function test_hours_are_the_fewest_of_any_type(): void
    {
        $csv = $this->csv(['2026-10-01'], types: ['pobór [kWh]']).$this->csv(['2026-10-01'], hours: 4, types: ['oddanie [kWh]']);

        $this->importer()->importString($csv);

        $this->assertSame(4, MeterDailyReading::first()->hours);
    }

    public function test_partial_day_is_filled_in_by_a_later_import(): void
    {
        $this->importer()->importString($this->csv(['2026-10-01'], hours: 4));
        $this->assertSame(4 * 0.101, MeterDailyReading::first()->t2_consumed);

        $result = $this->importer()->importString($this->csv(['2026-10-01']));

        $this->assertSame(['2026-10-01'], $result->imported);
        $this->assertSame([], $result->incomplete);
        $this->assertSame(24, MeterDailyReading::first()->hours);
        $this->assertSame(8 * 0.101, MeterDailyReading::first()->t2_consumed);
    }

    public function test_day_with_more_hours_is_not_overwritten_by_fewer(): void
    {
        $this->importer()->importString($this->csv(['2026-10-01']));

        $result = $this->importer()->importString($this->csv(['2026-10-01'], hours: 4));

        $this->assertSame([], $result->imported);
        $this->assertSame(['2026-10-01'], $result->skipped);
        $this->assertSame(24, MeterDailyReading::first()->hours);
    }

    public function test_dst_day_with_25_hours_is_complete(): void
    {
        $result = $this->importer()->importString($this->csv(['2026-10-25'], hours: 25));

        $this->assertSame(['2026-10-25'], $result->imported);
        $this->assertSame([], $result->incomplete);
    }

    public function test_dst_day_with_24_hours_is_incomplete(): void
    {
        $result = $this->importer()->importString($this->csv(['2026-10-25']));

        $this->assertSame(['2026-10-25'], $result->incomplete);
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

        Livewire::test('pages::meter-import.create')
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
     * @param  (callable(string, int): string)|null  $value  value per type and hour; defaults to 0,101 / 0,202 per type
     * @param  list<string>  $types  row types to include
     */
    private function csv(array $dates, int $hours = 24, ?array $skipHour = null, ?callable $value = null, array $types = self::TYPES): string
    {
        $lines = ['Data; Strefa; Wartość ;Rodzaj;Status;'];
        $value ??= fn (string $type) => ['pobór [kWh]' => '0,101', 'oddanie [kWh]' => '0,202'][$type];

        foreach ($types as $type) {
            foreach ($dates as $date) {
                for ($hour = 1; $hour <= $hours; $hour++) {
                    if ($skipHour === [$date, $hour]) {
                        continue;
                    }

                    $zone = $hour >= 7 && $hour <= 22 ? 'T1' : 'T2';
                    $lines[] = "{$date} {$hour}:00;{$zone};{$value($type, $hour)};{$type}; licznik; ";
                }
            }
        }

        return implode("\n", $lines)."\n";
    }
}
