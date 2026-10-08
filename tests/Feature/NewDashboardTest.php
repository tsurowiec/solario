<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NewDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('new.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_home_redirects_authenticated_users_to_the_new_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('home'))->assertRedirect(route('new.dashboard'));
    }

    public function test_dashboard_has_no_action_buttons(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('new.dashboard'))
            ->assertOk()
            ->assertDontSee(route('new.meter-import.create'))
            ->assertDontSee(route('new.pv-inverter.create'))
            ->assertDontSee(route('new.car-charges.create'));
    }

    public function test_dashboard_links_to_readings_and_car_charges(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::new.dashboard')
            ->assertSee(route('new.readings.index'))
            ->assertSee(route('new.car-charges.index'));
    }
}
