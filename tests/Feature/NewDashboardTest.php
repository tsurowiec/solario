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

    public function test_authenticated_users_see_the_action_buttons(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('new.dashboard'));
        $response->assertOk()
            ->assertSee('Import meter data')
            ->assertSee('Add car charge')
            ->assertSee('PV inverter data')
            ->assertSee(route('car-charges.create'));
    }
}
