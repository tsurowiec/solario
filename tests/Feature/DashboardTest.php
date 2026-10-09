<?php

namespace Tests\Feature;

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
}
