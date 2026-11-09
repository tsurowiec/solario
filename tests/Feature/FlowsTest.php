<?php

namespace Tests\Feature;

use App\Models\MeterDailyReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('flows.index'))->assertRedirect(route('login'));
    }

    public function test_sidebar_links_to_flows(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertOk()->assertSee(route('flows.index'));
    }

    public function test_shows_flows_for_every_month_newest_first(): void
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

        $this->get(route('flows.index'))
            ->assertOk()
            ->assertSeeInOrder(['October 2026 - Flows', 'September 2026 - Flows', 'August 2026 - Flows']);
    }

    public function test_shows_a_notice_without_data(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::flows.index')->assertSee('No data yet.');
    }
}
