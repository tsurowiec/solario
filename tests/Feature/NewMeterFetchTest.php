<?php

namespace Tests\Feature;

use App\Models\MeterDailyReading;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewMeterFetchTest extends TestCase
{
    use RefreshDatabase;

    private const LOGIN_URL = 'https://logowanie.tauron-dystrybucja.pl/login*';

    private const AUTH_URL = 'https://logowanie.tauron-dystrybucja.pl/realms/tauron/login-actions/authenticate*';

    private const DATA_URL = 'https://elicznik.tauron-dystrybucja.pl/energia/do/dane*';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.tauron.username' => 'user@example.com',
            'services.tauron.password' => 'secret',
            'services.tauron.site' => null,
            'services.tauron.lookback_days' => 7,
        ]);
    }

    public function test_meter_data_is_downloaded_and_imported(): void
    {
        $this->fakeTauron(csv: $this->csv(['2026-10-01', '2026-10-02']));

        $this->artisan('meter:fetch', ['--from' => '2026-10-01', '--to' => '2026-10-02'])
            ->expectsOutput('Imported 2 day(s): 2026-10-01, 2026-10-02')
            ->assertSuccessful();

        $this->assertSame(2, MeterDailyReading::count());

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://logowanie.tauron-dystrybucja.pl/realms/tauron/login-actions/authenticate?session_code=abc&execution=def'
            && $request['username'] === 'user@example.com'
            && $request['password'] === 'secret');

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://elicznik.tauron-dystrybucja.pl/energia/do/dane')
            && $request['form[from]'] === '01.10.2026'
            && $request['form[to]'] === '02.10.2026'
            && $request['form[type]'] === 'godzin'
            && $request['form[fileType]'] === 'CSV');
    }

    public function test_default_range_is_the_lookback_until_yesterday(): void
    {
        Carbon::setTestNow('2026-10-08 06:00:00');
        $this->fakeTauron(csv: $this->csv([]));

        $this->artisan('meter:fetch')->assertSuccessful();

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://elicznik.tauron-dystrybucja.pl/energia/do/dane')
            && $request['form[from]'] === '01.10.2026'
            && $request['form[to]'] === '07.10.2026');
    }

    public function test_metering_point_is_selected_when_configured(): void
    {
        config(['services.tauron.site' => '123_456_789']);
        $this->fakeTauron(csv: $this->csv([]));

        $this->artisan('meter:fetch')->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://elicznik.tauron-dystrybucja.pl/ustaw_punkt'
            && $request['site[client]'] === '123_456_789');
    }

    public function test_fails_when_the_login_form_is_missing(): void
    {
        $this->fakeTauron(loginPage: '<html>maintenance</html>');

        $this->artisan('meter:fetch')->expectsOutput('Tauron login form not found.')->assertFailed();
    }

    public function test_fails_when_the_credentials_are_wrong(): void
    {
        $this->fakeTauron(authResponse: $this->loginPage());

        $this->artisan('meter:fetch')
            ->expectsOutput('Tauron login failed: invalid username or password.')
            ->assertFailed();
    }

    public function test_fails_when_the_account_is_blocked(): void
    {
        // The faked response's effective URL is the request URL, so point the form at /blokada.
        Http::fake([
            self::LOGIN_URL => Http::response($this->loginPage('https://elicznik.tauron-dystrybucja.pl/blokada')),
            'https://elicznik.tauron-dystrybucja.pl/blokada' => Http::response('Blokada'),
        ]);

        $this->artisan('meter:fetch')
            ->expectsOutput('Tauron has temporarily blocked the account (too many requests).')
            ->assertFailed();
    }

    public function test_fails_without_importing_when_the_response_is_not_the_csv(): void
    {
        $this->fakeTauron(csv: '<html>Zaloguj się</html>');

        $this->artisan('meter:fetch')->expectsOutput('Tauron did not return the CSV export.')->assertFailed();

        $this->assertSame(0, MeterDailyReading::count());
    }

    public function test_fails_when_credentials_are_not_configured(): void
    {
        config(['services.tauron.password' => null]);
        Http::fake();

        $this->artisan('meter:fetch')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_fetch_is_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'meter:fetch'));

        $this->assertNotNull($event);
        $this->assertSame('0 6 * * *', $event->expression);
        $this->assertSame('Europe/Warsaw', $event->timezone);
    }

    private function fakeTauron(?string $loginPage = null, ?string $authResponse = null, string $csv = ''): void
    {
        Http::fake([
            self::LOGIN_URL => Http::response($loginPage ?? $this->loginPage()),
            self::AUTH_URL => Http::response($authResponse ?? '<html>eLicznik</html>'),
            'https://elicznik.tauron-dystrybucja.pl/ustaw_punkt' => Http::response(''),
            self::DATA_URL => Http::response($csv),
        ]);
    }

    private function loginPage(string $action = 'https://logowanie.tauron-dystrybucja.pl/realms/tauron/login-actions/authenticate?session_code=abc&amp;execution=def'): string
    {
        return '<html><form id="kc-form-login" onsubmit="return true;" action="'.$action.'" method="post"></form></html>';
    }

    /**
     * @param  list<string>  $dates
     */
    private function csv(array $dates): string
    {
        $lines = ['Data; Strefa; Wartość ;Rodzaj;Status;'];

        foreach (['pobór [kWh]' => '0,101', 'oddanie [kWh]' => '0,202'] as $type => $value) {
            foreach ($dates as $date) {
                for ($hour = 1; $hour <= 24; $hour++) {
                    $zone = $hour >= 7 && $hour <= 22 ? 'T1' : 'T2';
                    $lines[] = "{$date} {$hour}:00;{$zone};{$value};{$type}; licznik; ";
                }
            }
        }

        return implode("\n", $lines)."\n";
    }
}
