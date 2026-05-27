<?php

use App\Models\CarCharge;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Car Charges')]
#[Layout('layouts.app', ['title' => 'Car Charges'])]
class extends Component {
    use WithPagination;

    public string $car = '';

    public function updatedCar()
    {
        $this->resetPage();
    }

    public function rendering($view)
    {
        $query = CarCharge::orderBy('date', 'desc');

        if ($this->car !== '') {
            $query->where('car_id', $this->car);
        }

        $view->with('charges', $query->paginate(15));
        $view->with('cars', CarCharge::distinct()->pluck('car_id'));
    }

}; ?>

<div class="mx-auto max-w-2xl w-full space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Car Charges') }}</flux:heading>
        <flux:button href="{{ route('car-charges.create') }}" icon="plus" wire:navigate>{{ __('Add Charge') }}</flux:button>
    </div>

    <flux:select wire:model.live="car" class="max-w-48">
        <flux:select.option value="">{{ __('All Cars') }}</flux:select.option>
        @foreach ($cars as $carId)
            <flux:select.option :value="$carId">{{ ucfirst($carId) }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Car') }}</flux:table.column>
                <flux:table.column>{{ __('Charged (kWh)') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($charges as $charge)
                    <flux:table.row :key="$charge->id">
                        <flux:table.cell>{{ $charge->date->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>{{ ucfirst($charge->car_id) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($charge->charged) }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{ $charges->links() }}
</div>
