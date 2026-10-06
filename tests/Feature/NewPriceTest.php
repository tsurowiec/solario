<?php

namespace Tests\Feature;

use App\Models\Price;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NewPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_prices_keep_five_decimals(): void
    {
        $this->price('2026-01-01', 0.12345);

        $price = Price::first();

        $this->assertSame(0.12345, $price->peak_sell);
        $this->assertSame(0.12345, $price->network_monthly);
    }

    public function test_active_on_returns_the_latest_prices_started_by_the_date(): void
    {
        $this->price('2026-01-01', 0.1);
        $this->price('2026-07-01', 0.2);

        $this->assertNull(Price::activeOn(Carbon::parse('2025-12-31')));
        $this->assertSame(0.1, Price::activeOn(Carbon::parse('2026-06-30'))->peak_sell);
        $this->assertSame(0.2, Price::activeOn(Carbon::parse('2026-07-01'))->peak_sell);
        $this->assertSame(0.2, Price::activeOn(Carbon::parse('2026-10-06'))->peak_sell);
    }

    public function test_gross_per_kwh_doubles_quality_oze_and_cogen_and_adds_vat(): void
    {
        $price = $this->price('2026-01-01', 0);
        $price->fill([
            'peak_sell' => 0.5, 'peak_distr' => 0.3, 'peak_quality' => 0.01, 'peak_oze' => 0.002, 'peak_cogen' => 0.003,
            'off_peak_sell' => 0.4, 'off_peak_distr' => 0.1, 'off_peak_quality' => 0.02, 'off_peak_oze' => 0.004, 'off_peak_cogen' => 0.006,
        ])->save();

        $this->assertEqualsWithDelta((0.5 + 0.3 + 0.02 + 0.004 + 0.006) * 1.23, $price->grossPerKwh('peak'), 0.000001);
        $this->assertEqualsWithDelta((0.4 + 0.1 + 0.04 + 0.008 + 0.012) * 1.23, $price->grossPerKwh('off_peak'), 0.000001);
    }

    public function test_gross_monthly_sums_the_fees_and_adds_vat(): void
    {
        $price = $this->price('2026-01-01', 0);
        $price->fill(['sell_monthly' => 1, 'power_monthly' => 2, 'subscription_monthly' => 3.5, 'network_monthly' => 4.25])->save();

        $this->assertEqualsWithDelta(10.75, $price->monthlyFees(), 0.000001);
        $this->assertEqualsWithDelta(10.75 * 1.23, $price->grossMonthly(), 0.000001);
    }

    public function test_card_shows_gross_total_row(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-06');
        $this->price('2026-01-01', 0.1);

        // (0.1 + 0.1 + 2 × 0.1 × 3) × 1.23 = 0.984
        Livewire::test('pages::new.prices.index')
            ->assertSeeInOrder(['Cogeneration', 'Total incl. VAT', '~0.98400', '~0.98400', 'PLN/month', 'Network', 'Total incl. VAT', '0.49200']);   // 4 × 0.1 × 1.23
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('new.prices.index'))->assertRedirect(route('login'));
        $this->get(route('new.prices.create'))->assertRedirect(route('login'));
    }

    public function test_page_shows_current_card_and_hides_old_ones_behind_a_link(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-06');
        $this->price('2026-01-01', 0.11111);
        $this->price('2026-07-01', 0.22222);
        $this->price('2026-12-01', 0.33333);

        $html = Livewire::test('pages::new.prices.index')
            ->assertSee(route('new.prices.create'))
            ->assertSeeInOrder(['Since 01 Dec 2026', 'Upcoming', '0.33333', 'Since 01 Jul 2026', 'Current', '0.22222', 'Show old prices (1)', 'Since 01 Jan 2026', '0.11111'])
            ->html();

        // Old cards are rendered inside the hidden toggle section, greyed out.
        $this->assertMatchesRegularExpression('/x-show="showPrevious".*opacity-50.*Since 01 Jan 2026/s', $html);
    }

    public function test_no_prices_message(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::new.prices.index')
            ->assertSee('No prices yet.')
            ->assertDontSee('Show old prices');
    }

    public function test_create_form_is_prefilled_with_latest_prices_and_saves(): void
    {
        $this->actingAs(User::factory()->create());
        $this->price('2026-01-01', 0.12345);

        Livewire::test('pages::new.prices.form')
            ->assertSet('values.peak_sell', '0.12345')
            ->assertSet('values.network_monthly', '0.12345')
            ->set('since', '2026-11-01')
            ->set('values.peak_sell', '0.98765')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('new.prices.index'));

        $price = Price::activeOn(Carbon::parse('2026-11-01'));
        $this->assertSame('2026-11-01', $price->since->toDateString());
        $this->assertSame(0.98765, $price->peak_sell);
        $this->assertSame(0.12345, $price->off_peak_sell);
    }

    public function test_create_validates_date_and_decimals(): void
    {
        $this->actingAs(User::factory()->create());
        $this->price('2026-01-01', 0.1);

        Livewire::test('pages::new.prices.form')
            ->set('since', '2026-01-01')
            ->set('values.peak_sell', '0.123456')
            ->set('values.off_peak_sell', '-1')
            ->set('values.power_monthly', '')
            ->call('save')
            ->assertHasErrors(['since', 'values.peak_sell', 'values.off_peak_sell', 'values.power_monthly']);

        $this->assertSame(1, Price::count());
    }

    public function test_cards_link_to_edit(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-06');
        $old = $this->price('2026-01-01', 0.1);
        $current = $this->price('2026-07-01', 0.2);

        Livewire::test('pages::new.prices.index')
            ->assertSee(route('new.prices.edit', $current))
            ->assertSee(route('new.prices.edit', $old));
    }

    public function test_edit_page_shows_title_and_values_of_the_edited_prices(): void
    {
        $this->actingAs(User::factory()->create());
        $old = $this->price('2026-01-01', 0.11111);
        $this->price('2026-07-01', 0.22222);

        $this->get(route('new.prices.edit', $old))
            ->assertOk()
            ->assertSeeInOrder(['<title>', 'Edit Prices', '</title>'], false);

        Livewire::test('pages::new.prices.form', ['price' => $old])
            ->assertSet('since', '2026-01-01')
            ->assertSet('values.peak_sell', '0.11111');
    }

    public function test_prices_can_be_edited(): void
    {
        $this->actingAs(User::factory()->create());
        $price = $this->price('2026-10-06', 0.1);

        Livewire::test('pages::new.prices.form', ['price' => $price])
            ->set('since', '2026-10-01')
            ->set('values.peak_sell', '0.55555')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('new.prices.index'));

        $price->refresh();
        $this->assertSame('2026-10-01', $price->since->toDateString());
        $this->assertSame(0.55555, $price->peak_sell);
        $this->assertSame(1, Price::count());
    }

    public function test_edit_keeps_own_date_but_rejects_another_prices_date(): void
    {
        $this->actingAs(User::factory()->create());
        $this->price('2026-01-01', 0.1);
        $price = $this->price('2026-07-01', 0.2);

        Livewire::test('pages::new.prices.form', ['price' => $price])
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test('pages::new.prices.form', ['price' => $price])
            ->set('since', '2026-01-01')
            ->call('save')
            ->assertHasErrors('since');
    }

    public function test_prices_can_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-06');
        $old = $this->price('2026-01-01', 0.11111);
        $current = $this->price('2026-07-01', 0.22222);

        Livewire::test('pages::new.prices.index')
            ->assertSee('wire:click="delete('.$current->id.')"', false)
            ->call('delete', $current->id)
            ->assertSeeInOrder(['Since 01 Jan 2026', 'Current', '0.11111'])
            ->assertDontSee('0.22222');

        $this->assertModelMissing($current);
        $this->assertModelExists($old);
    }

    private function price(string $since, float $value): Price
    {
        return Price::create([
            'since' => $since,
            ...array_fill_keys([...Price::KWH_FIELDS, ...Price::MONTHLY_FIELDS], $value),
        ]);
    }
}
