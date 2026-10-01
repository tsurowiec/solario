<?php

use App\Models\Reading;
use Flux\Flux;
use Illuminate\Validation\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Reading')]
#[Layout('layouts.app', ['title' => 'Edit Reading'])]
class extends Component {

    public Reading $reading;

    public string $date = '';
    public int|string $pv_generated = '';
    public int|string $peak_consumed = '';
    public int|string $off_peak_consumed = '';
    public int|string $peak_fed_in = '';
    public int|string $off_peak_fed_in = '';

    public function mount(Reading $reading): void
    {
        $this->reading = $reading;
        $this->date = $reading->date->toDateString();
        $this->pv_generated = $reading->pv_generated;
        $this->peak_consumed = $reading->peak_consumed;
        $this->off_peak_consumed = $reading->off_peak_consumed;
        $this->peak_fed_in = $reading->peak_fed_in;
        $this->off_peak_fed_in = $reading->off_peak_fed_in;
    }

    public function save(): void
    {
        $fields = ['pv_generated', 'peak_consumed', 'off_peak_consumed', 'peak_fed_in', 'off_peak_fed_in'];

        $validated = $this->withValidator(function (Validator $v) use ($fields) {
            $v->after(function (Validator $v) use ($fields) {
                if ($v->errors()->isNotEmpty()) {
                    return;
                }

                $others = Reading::whereKeyNot($this->reading->id);
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

                foreach ($fields as $field) {
                    if ($previous && (int) $this->$field < $previous->$field) {
                        $v->errors()->add($field, __(
                            'Must be at least :min (reading on :date).',
                            ['min' => $previous->$field, 'date' => $previous->date->format('d M Y')]
                        ));
                    } elseif ($next && (int) $this->$field > $next->$field) {
                        $v->errors()->add($field, __(
                            'Must be at most :max (reading on :date).',
                            ['max' => $next->$field, 'date' => $next->date->format('d M Y')]
                        ));
                    }
                }
            });
        })->validate([
            'date'              => ['required', 'date'],
            'pv_generated'      => ['required', 'integer', 'min:0'],
            'peak_consumed'     => ['required', 'integer', 'min:0'],
            'off_peak_consumed' => ['required', 'integer', 'min:0'],
            'peak_fed_in'       => ['required', 'integer', 'min:0'],
            'off_peak_fed_in'   => ['required', 'integer', 'min:0'],
        ]);

        $this->reading->update($validated);

        Flux::toast(variant: 'success', text: __('Reading updated.'));

        $this->redirect(route('readings.index'), navigate: true);
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
        <flux:heading size="xl" class="mb-1">{{ __('Edit Reading') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Update the meter readings for the selected date.') }}</flux:subheading>

        <form wire:submit="save" class="space-y-5">

            <flux:card>
                <flux:input :label="__('Date')" type="text" id="date-picker" required />
                <flux:error name="date" />
                <flux:input wire:model="pv_generated" :label="__('PV Generated')" type="number" min="0" required class="pt-6" />
            </flux:card>

            <flux:card>
                <div class="flex justify-between gap-4">
                    <flux:input wire:model="peak_consumed" :label="__('Peak consumed')" type="number" min="0" required class="w-28" />
                    <flux:input wire:model="off_peak_consumed" :label="__('Off-Peak consumed')" type="number" min="0" required class="w-28" />
                </div>
            </flux:card>

            <flux:card>
                <div class="flex justify-between gap-4">
                    <flux:input wire:model="peak_fed_in" :label="__('Peak fed-in')" type="number" min="0" required class="w-28" />
                    <flux:input wire:model="off_peak_fed_in" :label="__('Off-Peak fed-in')" type="number" min="0" required class="w-28" />
                </div>
            </flux:card>

            <flux:button variant="primary" type="submit">
                {{ __('Save Reading') }}
            </flux:button>

        </form>
    </div>
</div>
