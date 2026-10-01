<?php

namespace Tests\Feature;

use App\Models\Reading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReadingEditTest extends TestCase
{
    use RefreshDatabase;

    private Reading $previous;

    private Reading $reading;

    private Reading $next;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->previous = $this->createReading('2026-01-01', 100);
        $this->reading = $this->createReading('2026-02-01', 200);
        $this->next = $this->createReading('2026-03-01', 300);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        auth()->logout();

        $response = $this->get(route('readings.edit', $this->reading));
        $response->assertRedirect(route('login'));
    }

    public function test_readings_list_links_to_edit_page(): void
    {
        $response = $this->get(route('readings.index'));
        $response->assertOk();
        $response->assertSee(route('readings.edit', $this->reading));
    }

    public function test_authenticated_users_can_visit_the_edit_page(): void
    {
        $response = $this->get(route('readings.edit', $this->reading));
        $response->assertOk();
    }

    public function test_reading_can_be_updated(): void
    {
        Livewire::test('pages::readings.edit', ['reading' => $this->reading])
            ->set('date', '2026-02-10')
            ->set('pv_generated', 250)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('readings.index'));

        $this->reading->refresh();
        $this->assertSame('2026-02-10', $this->reading->date->toDateString());
        $this->assertSame(250, $this->reading->pv_generated);
    }

    public function test_date_must_stay_between_neighbouring_readings(): void
    {
        Livewire::test('pages::readings.edit', ['reading' => $this->reading])
            ->set('date', '2026-01-01')
            ->call('save')
            ->assertHasErrors('date');

        Livewire::test('pages::readings.edit', ['reading' => $this->reading])
            ->set('date', '2026-03-01')
            ->call('save')
            ->assertHasErrors('date');
    }

    public function test_values_must_stay_between_neighbouring_readings(): void
    {
        Livewire::test('pages::readings.edit', ['reading' => $this->reading])
            ->set('peak_consumed', 99)
            ->call('save')
            ->assertHasErrors('peak_consumed');

        Livewire::test('pages::readings.edit', ['reading' => $this->reading])
            ->set('off_peak_fed_in', 301)
            ->call('save')
            ->assertHasErrors('off_peak_fed_in');

        $this->assertSame(200, $this->reading->fresh()->peak_consumed);
        $this->assertSame(200, $this->reading->fresh()->off_peak_fed_in);
    }

    public function test_latest_reading_has_no_upper_bound(): void
    {
        Livewire::test('pages::readings.edit', ['reading' => $this->next])
            ->set('date', '2026-04-01')
            ->set('pv_generated', 1000)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1000, $this->next->fresh()->pv_generated);
    }

    private function createReading(string $date, int $value): Reading
    {
        return Reading::create([
            'date' => $date,
            'pv_generated' => $value,
            'peak_consumed' => $value,
            'off_peak_consumed' => $value,
            'peak_fed_in' => $value,
            'off_peak_fed_in' => $value,
        ]);
    }
}
