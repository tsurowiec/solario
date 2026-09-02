<?php

use App\Models\Season;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Seasons')]
#[Layout('layouts.app', ['title' => 'Seasons'])]
class extends Component {
    use WithPagination;

    public function delete(Season $season): void
    {
        $season->delete();
    }

    public function rendering($view)
    {
        $view->with('seasons', Season::orderBy('starting_date', 'desc')->paginate(5));
    }

}; ?>

<div class="mx-auto max-w-5xl w-full space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Seasons') }}</flux:heading>
        <flux:button href="{{ route('seasons.create') }}" icon="plus" wire:navigate>{{ __('Add Season') }}</flux:button>
    </div>

    <flux:card class="landscape:hidden flex items-center gap-3 text-zinc-400">
        <flux:icon name="arrow-path" class="shrink-0 rotate-90" />
        <flux:text>{{ __('Rotate for charts') }}</flux:text>
    </flux:card>

    @foreach ($seasons as $season)
        <div class="space-y-4" wire:key="season-{{ $season->id }}">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ $season->name }}</flux:heading>
                    <flux:text class="text-zinc-400">
                        {{ $season->starting_date->format('d M Y') }} &ndash; {{ $season->endDate()->format('d M Y') }}
                    </flux:text>
                </div>
                <div class="flex items-center gap-1">
                    <flux:button size="xs" icon="pencil" href="{{ route('seasons.edit', $season) }}" wire:navigate />
                    <flux:button size="xs" variant="danger" icon="trash" wire:click="delete({{ $season->id }})" wire:confirm="{{ __('Delete this season?') }}" />
                </div>
            </div>

            <div class="portrait:hidden flex flex-col gap-4">
                <livewire:monthly-chart :season="$season->id" :key="'monthly-'.$season->id" />
                <livewire:energy-split-chart :season="$season->id" :key="'split-'.$season->id" />
            </div>
        </div>
    @endforeach

    {{ $seasons->links() }}
</div>
