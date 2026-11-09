<?php

use App\Services\EnergyUsage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Flows')]
#[Layout('layouts.app', ['title' => 'Flows'])]
class extends Component {

    /**
     * Every month with data, newest first (see EnergyUsage::months()).
     *
     * @return list<string>
     */
    #[Computed]
    public function months(): array
    {
        return app(EnergyUsage::class)->months();
    }

}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    @forelse ($this->months as $month)
        {{-- Each card scrolls sideways when the screen is too narrow. --}}
        <livewire:month-flows :month="$month" :key="'flows-'.$month" />
    @empty
        <flux:card>
            <flux:text>{{ __('No data yet.') }}</flux:text>
        </flux:card>
    @endforelse
</div>
