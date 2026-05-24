<?php

use App\Models\Reading;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Readings')]
#[Layout('layouts.app', ['title' => 'Readings'])]
class extends Component {

    #[Computed]
    public function readings()
    {
        return Reading::orderBy('date', 'desc')->get();
    }

}; ?>

<div class="mx-auto max-w-4xl w-full space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Readings') }}</flux:heading>
        <flux:button href="{{ route('readings.create') }}" icon="plus" wire:navigate>{{ __('Add Reading') }}</flux:button>
    </div>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('PV Generated') }}</flux:table.column>
                <flux:table.column>{{ __('Peak Consumed') }}</flux:table.column>
                <flux:table.column>{{ __('Off-Peak Consumed') }}</flux:table.column>
                <flux:table.column>{{ __('Peak Fed-In') }}</flux:table.column>
                <flux:table.column>{{ __('Off-Peak Fed-In') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->readings as $reading)
                    <flux:table.row :key="$reading->id">
                        <flux:table.cell>{{ $reading->date->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($reading->pv_generated) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($reading->peak_consumed) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($reading->off_peak_consumed) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($reading->peak_fed_in) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($reading->off_peak_fed_in) }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
