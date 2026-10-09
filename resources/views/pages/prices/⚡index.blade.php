<?php

use App\Models\Price;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Prices')]
#[Layout('layouts.app', ['title' => 'Prices'])]
class extends Component {

    public function delete(Price $price): void
    {
        $price->delete();

        unset($this->current, $this->upcoming, $this->previous);

        Flux::toast(variant: 'success', text: __('Prices deleted.'));
    }

    #[Computed]
    public function current(): ?Price
    {
        return Price::activeOn(today());
    }

    /** Prices that start after today, soonest first. */
    #[Computed]
    public function upcoming()
    {
        return Price::whereDate('since', '>', today())->orderBy('since')->get();
    }

    /** Prices replaced by the current ones, newest first. */
    #[Computed]
    public function previous()
    {
        if (! $this->current) {
            return collect();
        }

        return Price::whereDate('since', '<', $this->current->since)->orderByDesc('since')->get();
    }

}; ?>

<div class="mx-auto max-w-lg w-full space-y-6" x-data="{ showPrevious: false }">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Prices') }}</flux:heading>
        <flux:button href="{{ route('prices.create') }}" icon="plus" wire:navigate>{{ __('New prices') }}</flux:button>
    </div>

    @foreach ($this->upcoming as $price)
        <x-price-card :price="$price" :badge="__('Upcoming')" />
    @endforeach

    @if ($this->current)
        <x-price-card :price="$this->current" :badge="__('Current')" />
    @elseif ($this->upcoming->isEmpty())
        <flux:card>
            <flux:text>{{ __('No prices yet.') }}</flux:text>
        </flux:card>
    @endif

    @if ($this->previous->isNotEmpty())
        <div class="text-center">
            <button type="button" @click="showPrevious = !showPrevious" class="cursor-pointer text-sm text-zinc-500 underline underline-offset-4 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white">
                <span x-show="!showPrevious">{{ trans_choice('Show old prices (:count)|Show old prices (:count)', $this->previous->count()) }}</span>
                <span x-show="showPrevious" x-cloak>{{ __('Hide old prices') }}</span>
            </button>
        </div>

        <div x-show="showPrevious" x-cloak class="space-y-6">
            @foreach ($this->previous as $price)
                <x-price-card :price="$price" muted />
            @endforeach
        </div>
    @endif
</div>
