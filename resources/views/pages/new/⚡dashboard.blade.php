<?php

use App\Models\MeterDailyReading;
use App\Models\PvInverterReading;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')]
#[Layout('layouts.app', ['title' => 'Dashboard'])]
class extends Component {

    /**
     * The month of the latest day that can have both meter and PV inverter data,
     * but never before the first meter day (PV readings may start earlier).
     */
    #[Computed]
    public function month(): ?string
    {
        $firstMeter = MeterDailyReading::oldest('date')->value('date');
        $lastMeter = MeterDailyReading::latest('date')->value('date');
        $lastPv = PvInverterReading::latest('date')->value('date');

        if (! $lastMeter) {
            return null;
        }

        $latest = $lastPv ? max($firstMeter, min($lastMeter, $lastPv)) : $lastMeter;

        return Carbon::parse($latest)->startOfMonth()->toDateString();
    }

}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div class="flex flex-wrap gap-2">
        <flux:button href="{{ route('new.meter-import.create') }}" icon="arrow-up-tray" wire:navigate>{{ __('Import meter data') }}</flux:button>
        <flux:button href="{{ route('car-charges.create') }}" icon="bolt" wire:navigate>{{ __('Add car charge') }}</flux:button>
        <flux:button href="{{ route('new.pv-inverter.create') }}" icon="sun" wire:navigate>{{ __('PV inverter data') }}</flux:button>
    </div>

    @if ($this->month)
        <div class="grid auto-rows-min gap-4 landscape:grid-cols-2">
            <livewire:new.month-card :month="$this->month" :key="'month-'.$this->month" />
        </div>
    @endif
</div>
