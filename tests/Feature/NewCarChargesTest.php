<?php

namespace Tests\Feature;

use App\Models\CarCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        CarCharge::create(['date' => '2026-10-05', 'car_id' => 'tessy', 'charged' => 777]);

        Livewire::test('pages::new.car-charges.index')
            ->assertSet('month', '2026-10')
            ->assertSet('car', 'tesia')
            ->assertSeeInOrder(['20 Oct 2026', '01 Oct 2026'])  // all 20, no pagination
            ->assertDontSee('999')
            ->assertDontSee('777');
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
            ->call('selectCar', 'tessy')
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
            ->assertSeeInOrder(['02 Oct 2026', '01 Oct 2026', 'Total', '1,234']);
    }

    public function test_create_returns_to_the_list_of_the_new_charge(): void
    {
        $this->get(route('new.car-charges.index', ['car' => 'tessy']))
            ->assertSee(route('new.car-charges.create', ['car' => 'tessy']));

        Livewire::withQueryParams(['car' => 'tessy'])
            ->test('pages::new.car-charges.create')
            ->assertSet('car_id', 'tessy')
            ->set('date', '2026-09-15')
            ->set('charged', 33)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('new.car-charges.index', ['car' => 'tessy', 'month' => '2026-09']));

        $this->assertDatabaseHas('car_charges', ['car_id' => 'tessy', 'charged' => 33]);
    }

    public function test_create_validates_input(): void
    {
        Livewire::test('pages::new.car-charges.create')
            ->set('car_id', 'unknown')
            ->set('charged', '')
            ->call('save')
            ->assertHasErrors(['car_id', 'charged']);

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
            ->set('car_id', 'tessy')
            ->set('charged', 42)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('new.car-charges.index', ['car' => 'tessy', 'month' => '2026-09']));

        $charge->refresh();
        $this->assertSame('2026-09-28', $charge->date->toDateString());
        $this->assertSame('tessy', $charge->car_id);
        $this->assertSame(42, $charge->charged);
    }

    public function test_edit_validates_input(): void
    {
        $charge = CarCharge::create(['date' => '2026-10-01', 'car_id' => 'tesia', 'charged' => 10]);

        Livewire::test('pages::new.car-charges.edit', ['charge' => $charge])
            ->set('car_id', 'unknown')
            ->set('charged', -1)
            ->call('save')
            ->assertHasErrors(['car_id', 'charged']);
    }
}
