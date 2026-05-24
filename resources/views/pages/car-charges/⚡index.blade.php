<?php

use App\Models\CarCharge;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Car Charges')]
#[Layout('layouts.app', ['title' => 'Car Charges'])]
class extends Component {

    #[Computed]
    public function charges()
    {
        return CarCharge::orderBy('date', 'desc')->get();
    }

}; ?>

<div class="mx-auto max-w-2xl w-full space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Car Charges') }}</flux:heading>
        <flux:button href="{{ route('car-charges.create') }}" icon="plus" wire:navigate>{{ __('Add Charge') }}</flux:button>
    </div>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Car') }}</flux:table.column>
                <flux:table.column>{{ __('Charged (kWh)') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->charges as $charge)
                    <flux:table.row :key="$charge->id">
                        <flux:table.cell>{{ $charge->date->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>{{ ucfirst($charge->car_id) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($charge->charged) }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
