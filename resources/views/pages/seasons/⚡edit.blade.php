<?php

use App\Models\Season;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Season')]
#[Layout('layouts.app', ['title' => 'Edit Season'])]
class extends Component {

    public Season $season;

    public string $name = '';
    public string $starting_date = '';

    public function mount(Season $season): void
    {
        $this->season = $season;
        $this->name = $season->name;
        $this->starting_date = $season->starting_date->toDateString();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name'          => ['required', 'string', 'max:255'],
            'starting_date' => ['required', 'date', $this->uniqueDate()],
        ]);

        $this->season->update($validated);

        Flux::toast(variant: 'success', text: __('Season updated.'));

        $this->redirect(route('seasons.index'), navigate: true);
    }

    private function uniqueDate(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $exists = Season::whereDate('starting_date', $value)
                ->whereKeyNot($this->season->id)
                ->exists();

            if ($exists) {
                $fail(__('A season already starts on this date.'));
            }
        };
    }

}; ?>

@script
<script>
    flatpickr(document.getElementById('starting-date-picker'), {
        dateFormat: 'Y-m-d',
        defaultDate: $wire.starting_date,
        onChange: ([date]) => {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            $wire.starting_date = `${y}-${m}-${d}`;
        },
    });
</script>
@endscript

<div class="mx-auto max-w-lg w-full space-y-6">
    <div>
        <flux:heading size="xl" class="mb-1">{{ __('Edit Season') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('A season runs from its starting date until the day before the next season begins.') }}</flux:subheading>

        <form wire:submit="save" class="space-y-5">

            <flux:card>
                <flux:input wire:model="name" :label="__('Name')" type="text" required />
            </flux:card>

            <flux:card>
                <flux:input :label="__('Starting Date')" type="text" id="starting-date-picker" required />
                <flux:error name="starting_date" />
            </flux:card>

            <flux:button variant="primary" type="submit">
                {{ __('Save Season') }}
            </flux:button>

        </form>
    </div>
</div>
