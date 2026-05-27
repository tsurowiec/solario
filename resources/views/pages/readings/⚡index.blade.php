<?php

use App\Models\Reading;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Readings')]
#[Layout('layouts.app', ['title' => 'Readings'])]
class extends Component {
    use WithPagination;

    public function delete(Reading $reading): void
    {
        if ($this->undeletableIds()->contains($reading->id)) {
            return;
        }

        $reading->delete();
    }

    public function rendering($view)
    {
        $view->with('readings', Reading::orderBy('date', 'desc')->paginate(15));
        $view->with('undeletableIds', $this->undeletableIds());
    }

    private function undeletableIds()
    {
        $ids = Reading::query()
            ->joinSub(
                Reading::selectRaw("max(date) as max_date, strftime('%Y-%m', date) as ym")->groupByRaw("strftime('%Y-%m', date)"),
                'latest',
                fn ($join) => $join->on('readings.date', '=', 'latest.max_date'),
            )
            ->pluck('readings.id');

        $firstId = Reading::orderBy('date')->value('id');

        if ($firstId) {
            $ids->push($firstId);
        }

        return $ids->unique();
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
                <flux:table.column class="w-0" />
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($readings as $reading)
                    <flux:table.row :key="$reading->id">
                        <flux:table.cell>{{ $reading->date->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($reading->pv_generated) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($reading->peak_consumed) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($reading->off_peak_consumed) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($reading->peak_fed_in) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($reading->off_peak_fed_in) }}</flux:table.cell>
                        <flux:table.cell>
                            @unless ($undeletableIds->contains($reading->id))
                                <flux:button variant="danger" size="xs" icon="trash" wire:click="delete({{ $reading->id }})" wire:confirm="{{ __('Are you sure you want to delete this reading?') }}" />
                            @endunless
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{ $readings->links() }}
</div>
