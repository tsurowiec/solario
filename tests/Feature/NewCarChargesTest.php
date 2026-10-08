<?php

namespace Tests\Feature;

use App\Models\CarCharge;
use App\Models\MeterDailyReading;
use App\Models\Price;
use App\Models\PvInverterReading;
use App\Models\User;
use App\Services\EnergyUsage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class NewCarChargesTest extends TestCase
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

        $this->get(route('new.car-charges.index'))->assertRedirect(route('login'));
    }

    public function test_shows_all_charges_of_the_current_month_by_default(): void
    {
        for ($day = 1; $day <= 20; $day++) {
            CarCharge::create(['date' => sprintf('2026-10-%02d', $day), 'car_id' => 'tesia', 'charged' => 100 + $day]);
        }
        CarCharge::create(['date' => '2026-09-30', 'car_id' => 'tesia', 'charged' => 999]);

        Livewire::test('pages::new.car-charges.index')
            ->assertSet('month', '2026-10')
            ->assertSeeInOrder(['20 Oct 2026', '01 Oct 2026'])  // all 20, no pagination
            ->assertDontSee('999');
    }

    public function test_renders_a_table_per_car_with_its_own_add_button(): void
    {
        CarCharge::create(['date' => '2026-10-04', 'car_id' => 'tesia', 'charged' => 444]);
        CarCharge::create(['date' => '2026-10-05', 'car_id' => 'tessy', 'charged' => 777]);

        Livewire::test('pages::new.car-charges.index')
            ->assertSeeInOrder([
                'Tesia', route('new.car-charges.create', ['car' => 'tesia']), '444', 'Total', '444',
                'Tessy', route('new.car-charges.create', ['car' => 'tessy']), '777', 'Total', '777',
            ], false);
    }

    public function test_car_without_charges_in_the_month_shows_an_empty_state(): void
    {
        CarCharge::create(['date' => '2026-10-05', 'car_id' => 'tessy', 'charged' => 777]);

        Livewire::test('pages::new.car-charges.index')
            ->assertSeeInOrder(['Tesia', 'No charges in this month.', 'Tessy', '777']);
    }

    public function test_month_dropdown_lists_months_with_charges_and_switches(): void
    {
        CarCharge::create(['date' => '2026-08-15', 'car_id' => 'tesia', 'charged' => 815]);
        CarCharge::create(['date' => '2026-05-26', 'car_id' => 'tessy', 'charged' => 526]);

        Livewire::test('pages::new.car-charges.index')
            ->assertSeeInOrder(['October 2026', 'August 2026', 'May 2026'])
            ->assertSee('No charges in this month.')
            ->set('month', '2026-08')
            ->assertSee('815')
            ->set('month', '2026-05')
            ->assertSee('526');
    }

    public function test_merge_button_is_gone_and_edit_is_next_to_delete(): void
    {
        $charge = CarCharge::create(['date' => '2026-10-01', 'car_id' => 'tesia', 'charged' => 10]);
        CarCharge::create(['date' => '2026-10-02', 'car_id' => 'tesia', 'charged' => 20]);

        $html = Livewire::test('pages::new.car-charges.index')
            ->assertDontSee('merge(')
            ->html();

        $this->assertMatchesRegularExpression(
            '~/new/car-charges/'.$charge->id.'/edit.*wire:click="delete\('.$charge->id.'\)"~s',
            $html
        );
    }

    public function test_total_row_sums_the_month(): void
    {
        CarCharge::create(['date' => '2026-10-01', 'car_id' => 'tesia', 'charged' => 1000]);
        CarCharge::create(['date' => '2026-10-02', 'car_id' => 'tesia', 'charged' => 234]);
        CarCharge::create(['date' => '2026-09-30', 'car_id' => 'tesia', 'charged' => 5]);

        Livewire::test('pages::new.car-charges.index')
            ->assertSeeInOrder(['02 Oct 2026', '01 Oct 2026', 'Total', '1,234'])
            ->assertDontSee('PLN');  // no energy data / prices
    }

    public function test_total_row_shows_the_amount_at_the_average_price_of_the_month(): void
    {
        Price::create(['since' => '2026-10-01', ...array_fill_keys([...Price::KWH_FIELDS, ...Price::MONTHLY_FIELDS], 0.1)]);
        PvInverterReading::create(['date' => '2026-09-30', 'value' => 1000]);
        PvInverterReading::create(['date' => '2026-10-05', 'value' => 1100]);
        foreach (['2026-10-01', '2026-10-02'] as $date) {
            MeterDailyReading::create([
                'date' => $date,
                't1_consumed' => 1, 't2_consumed' => 2, 't1_fed_in' => 3, 't2_fed_in' => 4,
                't1_balanced_consumed' => 5, 't2_balanced_consumed' => 3,
                't1_balanced_fed_in' => 2.5, 't2_balanced_fed_in' => 1,
            ]);
        }
        CarCharge::create(['date' => '2026-10-01', 'car_id' => 'tesia', 'charged' => 10]);
        CarCharge::create(['date' => '2026-10-04', 'car_id' => 'tesia', 'charged' => 5]);

        $pricePerUnit = app(EnergyUsage::class)->month(Carbon::parse('2026-10-01'))->pricePerUnit;
        $this->assertGreaterThan(0, $pricePerUnit);

        Livewire::test('pages::new.car-charges.index')
            ->assertSeeInOrder(['Total', '15 kWh', number_format(15 * $pricePerUnit, 2).' PLN']);
    }

    public function test_create_returns_to_the_list_of_the_new_charge(): void
    {
        Livewire::withQueryParams(['car' => 'tessy'])
            ->test('pages::new.car-charges.create')
            ->assertSet('car_id', 'tessy')
            ->set('date', '2026-09-15')
            ->set('charged', 33)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('new.car-charges.index', ['month' => '2026-09']));

        $this->assertDatabaseHas('car_charges', ['car_id' => 'tessy', 'charged' => 33]);
    }

    public function test_cancel_returns_to_the_list_of_the_month(): void
    {
        $charge = CarCharge::create(['date' => '2026-08-15', 'car_id' => 'tesia', 'charged' => 10]);

        Livewire::test('pages::new.car-charges.create')
            ->assertSeeHtml('href="'.route('new.car-charges.index').'"');

        Livewire::withQueryParams(['car' => 'tessy', 'month' => '2026-08'])
            ->test('pages::new.car-charges.index')
            ->assertSeeHtml(e(route('new.car-charges.create', ['car' => 'tessy', 'month' => '2026-08'])));

        Livewire::withQueryParams(['car' => 'tessy', 'month' => '2026-08'])
            ->test('pages::new.car-charges.create')
            ->assertSee('Cancel')
            ->assertSeeHtml(route('new.car-charges.index', ['month' => '2026-08']));

        Livewire::test('pages::new.car-charges.edit', ['charge' => $charge])
            ->assertSee('Cancel')
            ->assertSeeHtml(route('new.car-charges.index', ['month' => '2026-08']));
    }

    public function test_create_validates_input(): void
    {
        Livewire::test('pages::new.car-charges.create')
            ->set('charged', '')
            ->call('save')
            ->assertHasErrors(['charged']);

        $this->assertDatabaseCount('car_charges', 0);
    }

    public function test_charge_can_be_deleted(): void
    {
        $charge = CarCharge::create(['date' => '2026-10-01', 'car_id' => 'tesia', 'charged' => 10]);

        Livewire::test('pages::new.car-charges.index')
            ->call('delete', $charge->id)
            ->assertSee('No charges in this month.');

        $this->assertModelMissing($charge);
    }

    public function test_charge_can_be_edited(): void
    {
        $charge = CarCharge::create(['date' => '2026-10-01', 'car_id' => 'tesia', 'charged' => 10]);

        Livewire::test('pages::new.car-charges.edit', ['charge' => $charge])
            ->assertSet('charged', 10)
            ->set('date', '2026-09-28')
            ->set('charged', 42)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('new.car-charges.index', ['month' => '2026-09']));

        $charge->refresh();
        $this->assertSame('2026-09-28', $charge->date->toDateString());
        $this->assertSame('tesia', $charge->car_id);
        $this->assertSame(42, $charge->charged);
    }

    public function test_edit_validates_input(): void
    {
        $charge = CarCharge::create(['date' => '2026-10-01', 'car_id' => 'tesia', 'charged' => 10]);

        Livewire::test('pages::new.car-charges.edit', ['charge' => $charge])
            ->set('charged', -1)
            ->call('save')
            ->assertHasErrors(['charged']);
    }

    public function test_car_cannot_be_changed_in_the_create_form(): void
    {
        $component = Livewire::withQueryParams(['car' => 'tessy'])
            ->test('pages::new.car-charges.create')
            ->assertSeeHtml('disabled');

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('car_id', 'tesia');
    }

    public function test_car_cannot_be_changed_in_the_edit_form(): void
    {
        $charge = CarCharge::create(['date' => '2026-10-01', 'car_id' => 'tesia', 'charged' => 10]);

        $component = Livewire::test('pages::new.car-charges.edit', ['charge' => $charge])
            ->assertSeeHtml('disabled');

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('car_id', 'tessy');
    }
}
