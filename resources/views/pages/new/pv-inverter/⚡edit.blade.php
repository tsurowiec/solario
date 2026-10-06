<?php

use App\Models\PvInverterReading;
use Flux\Flux;
use Illuminate\Validation\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit PV Inverter Reading')]
#[Layout('layouts.app', ['title' => 'Edit PV Inverter Reading'])]
class extends Component {

    public PvInverterReading $reading;

    public string $date = '';
    public int|string $value = '';

    public function mount(PvInverterReading $reading): void
    {
        $this->reading = $reading;
        $this->date = $reading->date->toDateString();
        $this->value = $reading->value;
    }

    public function save(): void
    {
        $validated = $this->withValidator(function (Validator $v) {
            $v->after(function (Validator $v) {
                if ($v->errors()->isNotEmpty()) {
                    return;
                }

                $others = PvInverterReading::whereKeyNot($this->reading->id);
                $previous = (clone $others)->whereDate('date', '<', $this->reading->date)->latest('date')->first();
                $next = (clone $others)->whereDate('date', '>', $this->reading->date)->oldest('date')->first();

                if ($previous && $this->date <= $previous->date->toDateString()) {
                    $v->errors()->add('date', __(
                        'Date must be after the previous reading (:date).',
                        ['date' => $previous->date->format('d M Y')]
                    ));
                }

                if ($next && $this->date >= $next->date->toDateString()) {
                    $v->errors()->add('date', __(
                        'Date must be before the next reading (:date).',
                        ['date' => $next->date->format('d M Y')]
                    ));
                }

                if ($previous && (int) $this->value < $previous->value) {
                    $v->errors()->add('value', __(
                        'Must be at least :min (reading on :date).',
                        ['min' => $previous->value, 'date' => $previous->date->format('d M Y')]
                    ));
                } elseif ($next && (int) $this->value > $next->value) {
                    $v->errors()->add('value', __(
                        'Must be at most :max (reading on :date).',
                        ['max' => $next->value, 'date' => $next->date->format('d M Y')]
                    ));
                }
            });
        })->validate([
            'date'  => ['required', 'date'],
            'value' => ['required', 'integer', 'min:0'],
        ]);

        $this->reading->update($validated);

        Flux::toast(variant: 'success', text: __('PV inverter reading updated.'));

        $this->redirect(route('new.readings.index'), navigate: true);
    }

}; ?>

@script
<script>
    flatpickr(document.getElementById('date-picker'), {
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
    <div>
        <flux:heading size="xl" class="mb-1">{{ __('Edit PV Inverter Reading') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Update the PV inverter reading for the selected date.') }}</flux:subheading>

        <form wire:submit="save" class="space-y-5">
            <flux:card>
                <flux:input :label="__('Date')" type="text" id="date-picker" required />
                <flux:error name="date" />
                <flux:input wire:model="value" :label="__('PV Generated')" type="number" min="0" required class="pt-6" />
            </flux:card>

            <flux:button variant="primary" type="submit">
                {{ __('Save Reading') }}
            </flux:button>
        </form>
    </div>
</div>
