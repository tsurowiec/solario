<?php

use App\Models\PvInverterReading;
use Flux\Flux;
use Illuminate\Validation\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('PV Inverter Data')]
#[Layout('layouts.app', ['title' => 'PV Inverter Data'])]
class extends Component {

    public string $date = '';
    public int|string $value = '';

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    #[Computed]
    public function last(): ?PvInverterReading
    {
        return PvInverterReading::latest('date')->first();
    }

    public function save(): void
    {
        $validated = $this->withValidator(function (Validator $v) {
            $v->after(function (Validator $v) {
                if ($v->errors()->isNotEmpty()) {
                    return;
                }

                $last = $this->last;

                if (! $last) {
                    return;
                }

                if ($this->date <= $last->date->toDateString()) {
                    $v->errors()->add('date', __(
                        'Date must be after the last reading (:date).',
                        ['date' => $last->date->format('d M Y')]
                    ));
                    return;
                }

                if ((int) $this->value < $last->value) {
                    $v->errors()->add('value', __(
                        'Must be at least :min (last reading on :date).',
                        ['min' => $last->value, 'date' => $last->date->format('d M Y')]
                    ));
                }
            });
        })->validate([
            'date'  => ['required', 'date'],
            'value' => ['required', 'integer', 'min:0'],
        ]);

        PvInverterReading::create($validated);

        Flux::toast(variant: 'success', text: __('PV inverter reading saved.'));

        $this->redirect(route('readings.index'), navigate: true);
    }

}; ?>

@script
<script>
    let fp = flatpickr(document.getElementById('date-picker'), {
        dateFormat: 'Y-m-d',
        defaultDate: $wire.date,
        onChange: ([date]) => {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            $wire.date = `${y}-${m}-${d}`;
        },
    });
</script>
@endscript

<div class="mx-auto max-w-lg w-full space-y-6">
    <flux:card>
        @if ($this->last)
            <flux:heading class="mb-4">
                {{ __('Last Reading') }}
                <flux:badge class="ml-2" size="sm" variant="outline">{{ $this->last->date->format('d M Y') }}</flux:badge>
            </flux:heading>
            <div class="flex items-center gap-2">
                <flux:icon name="sun" class="text-yellow-400 size-8 shrink-0" />
                <flux:text class="font-medium big">{{ number_format($this->last->value) }} <span class="text-zinc-400 font-normal text-xs">kWh</span></flux:text>
            </div>
        @else
            <flux:heading class="mb-1">{{ __('Last Reading') }}</flux:heading>
            <flux:text>{{ __('No readings yet.') }}</flux:text>
        @endif
    </flux:card>

    <div>
        <flux:heading size="xl" class="mb-1">{{ __('PV Inverter Data') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Enter the PV inverter reading for the selected date.') }}</flux:subheading>

        <form wire:submit="save" class="space-y-5">
            <flux:card>
                <flux:input :label="__('Date')" type="text" id="date-picker" required class="pb-6"/>
                <flux:input wire:model="value" :label="__('PV Generated')" type="number" min="0" required />
            </flux:card>

            <flux:button variant="primary" type="submit">
                {{ __('Save Reading') }}
            </flux:button>
        </form>
    </div>
</div>
