<?php

use App\Models\CarCharge;
use App\Services\EnergyUsage;
use Carbon\Carbon;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Car Charges')]
#[Layout('layouts.app', ['title' => 'Car Charges'])]
class extends Component {

    /** Selected month (Y-m); the current month by default. */
    #[Url]
    public string $month = '';

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = today()->format('Y-m');
        }
    }

    public function delete(int $id): void
    {
        CarCharge::findOrFail($id)->delete();

        unset($this->charges, $this->months);

        Flux::toast(variant: 'success', text: __('Charge deleted.'));
    }

    /**
     * Months to choose from (Y-m => label), newest first: every month with a charge, plus the current and selected month.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function months(): array
    {
        return CarCharge::pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m'))
            ->push(today()->format('Y-m'), $this->month)
            ->unique()
            ->sortDesc()
            ->mapWithKeys(fn (string $ym) => [$ym => Carbon::createFromFormat('!Y-m', $ym)->format('F Y')])
            ->all();
    }

    /** Average price of the selected month (PLN/kWh, from the dashboard summary); null without data or prices. */
    #[Computed]
    public function pricePerUnit(): ?float
    {
        return app(EnergyUsage::class)->month(Carbon::createFromFormat('!Y-m', $this->month))?->pricePerUnit;
    }

    /**
     * Charges of the selected month per car (car id => charges), newest first.
     *
     * @return array<string, \Illuminate\Support\Collection<int, CarCharge>>
     */
    #[Computed]
    public function charges(): array
    {
        $start = Carbon::createFromFormat('!Y-m', $this->month);

        $charges = CarCharge::whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $start->copy()->endOfMonth()->toDateString())
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('car_id');

        return collect(CarCharge::CARS)
            ->mapWithKeys(fn (string $car) => [$car => $charges->get($car, collect())])
            ->all();
    }

}; ?>

<div class="mx-auto max-w-2xl w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('Car Charges') }}</flux:heading>

        <flux:select wire:model.live="month" class="max-w-48" :aria-label="__('Month')">
            @foreach ($this->months as $value => $label)
                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @foreach ($this->charges as $carId => $charges)
        <flux:card wire:key="car-{{ $carId }}" class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg" class="flex items-center gap-2">
                    <flux:icon name="bolt" :class="(\App\Models\CarCharge::COLORS[$carId] ?? 'text-zinc-400').' shrink-0'" />
                    {{ ucfirst($carId) }}
                </flux:heading>
                <flux:button size="sm" href="{{ route('car-charges.create', ['car' => $carId, 'month' => $month]) }}" icon="plus" wire:navigate>{{ __('Add Charge') }}</flux:button>
            </div>

            @if ($charges->isEmpty())
                <flux:text>{{ __('No charges in this month.') }}</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Charged') }}</flux:table.column>
                        <flux:table.column class="w-0" />
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($charges as $charge)
                            <flux:table.row :key="$charge->id">
                                <flux:table.cell>{{ $charge->date->format('d M Y') }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format($charge->charged) }} kWh</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button size="xs" icon="pencil" href="{{ route('car-charges.edit', $charge) }}" wire:navigate :aria-label="__('Edit')" />
                                        <flux:button size="xs" variant="danger" icon="trash" wire:click="delete({{ $charge->id }})" wire:confirm="{{ __('Delete this charge?') }}" :aria-label="__('Delete')" />
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                    <flux:table.rows>
                        <flux:table.row>
                            <flux:table.cell class="font-semibold">{{ __('Total') }}</flux:table.cell>
                            <flux:table.cell align="end" class="font-semibold">{{ number_format($charges->sum('charged')) }} kWh</flux:table.cell>
                            <flux:table.cell align="end" class="font-semibold whitespace-nowrap">
                                @if ($this->pricePerUnit !== null)
                                    {{ number_format($charges->sum('charged') * $this->pricePerUnit, 2) }} PLN
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endforeach
</div>
