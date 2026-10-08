<?php

use App\Models\CarCharge;
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

    #[Url]
    public string $car = '';

    /** Selected month (Y-m); the current month by default. */
    #[Url]
    public string $month = '';

    public function mount(): void
    {
        if (! in_array($this->car, CarCharge::CARS, true)) {
            $this->car = CarCharge::CARS[0];
        }

        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = today()->format('Y-m');
        }
    }

    public function selectCar(string $car): void
    {
        if (in_array($car, CarCharge::CARS, true)) {
            $this->car = $car;
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

    #[Computed]
    public function charges()
    {
        $start = Carbon::createFromFormat('!Y-m', $this->month);

        return CarCharge::where('car_id', $this->car)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $start->copy()->endOfMonth()->toDateString())
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();
    }

}; ?>

<div class="mx-auto max-w-2xl w-full space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Car Charges') }}</flux:heading>
        <flux:button href="{{ route('new.car-charges.create', ['car' => $car]) }}" icon="plus" wire:navigate>{{ __('Add Charge') }}</flux:button>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:button.group>
            @foreach (\App\Models\CarCharge::CARS as $carId)
                <flux:button
                    wire:click="selectCar('{{ $carId }}')"
                    :variant="$car === $carId ? 'primary' : 'filled'"
                >{{ ucfirst($carId) }}</flux:button>
            @endforeach
        </flux:button.group>

        <flux:select wire:model.live="month" class="max-w-48" :aria-label="__('Month')">
            @foreach ($this->months as $value => $label)
                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:card>
        @if ($this->charges->isEmpty())
            <flux:text>{{ __('No charges in this month.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Car') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Charged') }}</flux:table.column>
                    <flux:table.column class="w-0" />
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->charges as $charge)
                        <flux:table.row :key="$charge->id">
                            <flux:table.cell>{{ $charge->date->format('d M Y') }}</flux:table.cell>
                            <flux:table.cell>{{ ucfirst($charge->car_id) }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($charge->charged) }} kWh</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button size="xs" icon="pencil" href="{{ route('new.car-charges.edit', $charge) }}" wire:navigate :aria-label="__('Edit')" />
                                    <flux:button size="xs" variant="danger" icon="trash" wire:click="delete({{ $charge->id }})" wire:confirm="{{ __('Delete this charge?') }}" :aria-label="__('Delete')" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
                <flux:table.rows>
                    <flux:table.row>
                        <flux:table.cell class="font-semibold">{{ __('Total') }}</flux:table.cell>
                        <flux:table.cell />
                        <flux:table.cell align="end" class="font-semibold">{{ number_format($this->charges->sum('charged')) }} kWh</flux:table.cell>
                        <flux:table.cell />
                    </flux:table.row>
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
