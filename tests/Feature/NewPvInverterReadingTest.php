<?php

namespace Tests\Feature;

use App\Models\PvInverterReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NewPvInverterReadingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        auth()->logout();

        $this->get(route('new.pv-inverter.create'))->assertRedirect(route('login'));
    }

    public function test_readings_page_links_to_the_form(): void
    {
        $this->get(route('new.readings.index'))->assertSee(route('new.pv-inverter.create'));
    }

    public function test_reading_is_saved(): void
    {
        Livewire::test('pages::new.pv-inverter.create')
            ->set('date', '2026-10-06')
            ->set('value', 1234)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('new.readings.index'));

        $this->assertDatabaseHas('pv_inverter_readings', ['value' => 1234]);
        $this->assertSame('2026-10-06', PvInverterReading::first()->date->toDateString());
    }

    public function test_date_must_be_after_the_last_reading(): void
    {
        PvInverterReading::create(['date' => '2026-10-01', 'value' => 100]);

        Livewire::test('pages::new.pv-inverter.create')
            ->set('date', '2026-10-01')
            ->set('value', 200)
            ->call('save')
            ->assertHasErrors('date');
    }

    public function test_value_cannot_be_lower_than_the_last_reading(): void
    {
        PvInverterReading::create(['date' => '2026-10-01', 'value' => 100]);

        Livewire::test('pages::new.pv-inverter.create')
            ->set('date', '2026-10-02')
            ->set('value', 99)
            ->call('save')
            ->assertHasErrors('value');
    }
}
