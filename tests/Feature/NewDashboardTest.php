<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('new.dashboard'));
        $response->assertRedirect(route('login'));
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
}
