<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')]
#[Layout('layouts.app', ['title' => 'Dashboard'])]
class extends Component {
    //
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div class="flex flex-wrap gap-2">
        <flux:button href="{{ route('new.meter-import.create') }}" icon="arrow-up-tray" wire:navigate>{{ __('Import meter data') }}</flux:button>
        <flux:button href="{{ route('car-charges.create') }}" icon="bolt" wire:navigate>{{ __('Add car charge') }}</flux:button>
        <flux:button href="{{ route('new.pv-inverter.create') }}" icon="sun" wire:navigate>{{ __('PV inverter data') }}</flux:button>
    </div>
</div>
