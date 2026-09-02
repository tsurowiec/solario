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
        $view->with('seasons', Season::orderBy('starting_date', 'desc')->paginate(15));
    }

}; ?>

<div class="mx-auto max-w-2xl w-full space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Seasons') }}</flux:heading>
        <flux:button href="{{ route('seasons.create') }}" icon="plus" wire:navigate>{{ __('Add Season') }}</flux:button>
    </div>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Starts') }}</flux:table.column>
                <flux:table.column>{{ __('Ends') }}</flux:table.column>
                <flux:table.column class="w-0" />
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($seasons as $season)
                    <flux:table.row :key="$season->id">
                        <flux:table.cell>{{ $season->name }}</flux:table.cell>
                        <flux:table.cell>{{ $season->starting_date->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $season->endDate()->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center justify-end gap-1">
                                <flux:button size="xs" icon="pencil" href="{{ route('seasons.edit', $season) }}" wire:navigate />
                                <flux:button size="xs" variant="danger" icon="trash" wire:click="delete({{ $season->id }})" wire:confirm="{{ __('Delete this season?') }}" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{ $seasons->links() }}
</div>
