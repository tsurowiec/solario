<?php

use App\Models\Season;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add Season')]
#[Layout('layouts.app', ['title' => 'Add Season'])]
class extends Component {

    public string $name = '';
    public string $starting_date = '';
    public float $peak_rate = 1.40;
    public float $off_peak_rate = 0.70;
    public float $fed_in_ratio = 0.80;

    public function mount(): void
    {
        $this->starting_date = now()->toDateString();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name'          => ['required', 'string', 'max:255'],
            'starting_date' => ['required', 'date', $this->uniqueDate()],
            'peak_rate'     => ['required', 'numeric', 'min:0'],
            'off_peak_rate' => ['required', 'numeric', 'min:0'],
            'fed_in_ratio'  => ['required', 'numeric', 'min:0'],
        ]);

        Season::create($validated);

        Flux::toast(variant: 'success', text: __('Season saved.'));

        $this->redirect(route('seasons.index'), navigate: true);
    }

    private function uniqueDate(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (Season::whereDate('starting_date', $value)->exists()) {
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
        <flux:heading size="xl" class="mb-1">{{ __('Add Season') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('A season runs from its starting date until the day before the next season begins.') }}</flux:subheading>

        <form wire:submit="save" class="space-y-5">

            <flux:card>
                <flux:input wire:model="name" :label="__('Name')" type="text" required />
            </flux:card>

            <flux:card>
                <flux:input :label="__('Starting Date')" type="text" id="starting-date-picker" required />
                <flux:error name="starting_date" />
            </flux:card>

            <flux:card class="space-y-5">
                <flux:input wire:model="peak_rate" :label="__('Peak Rate (PLN/kWh)')" type="number" step="0.01" min="0" required />
                <flux:input wire:model="off_peak_rate" :label="__('Off-Peak Rate (PLN/kWh)')" type="number" step="0.01" min="0" required />
                <flux:input wire:model="fed_in_ratio" :label="__('Fed-In Ratio')" type="number" step="0.01" min="0" required />
            </flux:card>

            <flux:button variant="primary" type="submit">
                {{ __('Save Season') }}
            </flux:button>

        </form>
    </div>
</div>
