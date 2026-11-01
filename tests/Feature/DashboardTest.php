<?php

namespace Tests\Feature;

use App\Models\MeterDailyReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_home_redirects_authenticated_users_to_the_new_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('home'))->assertRedirect(route('dashboard'));
    }

    public function test_dashboard_has_no_action_buttons(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('meter-import.create'))
            ->assertDontSee(route('pv-inverter.create'))
            ->assertDontSee(route('car-charges.create'));
    }

    public function test_dashboard_links_to_readings_and_car_charges(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::dashboard')
            ->assertSee(route('readings.index'))
            ->assertSee(route('car-charges.index'));
    }

    public function test_dashboard_shows_a_card_for_every_month_newest_first(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['2026-08-20', '2026-10-05'] as $date) {
            MeterDailyReading::create([
                'date' => $date,
                't1_consumed' => 1, 't2_consumed' => 2,
                't1_fed_in' => 3, 't2_fed_in' => 4,
                't1_balanced_consumed' => 1, 't2_balanced_consumed' => 2,
                't1_balanced_fed_in' => 0, 't2_balanced_fed_in' => 0,
            ]);
        }

        Livewire::test('pages::dashboard')
            ->assertSeeInOrder(['October 2026', 'September 2026', 'August 2026']);
    }
}
