<?php

use App\Models\Reading;
use Flux\Flux;
use Illuminate\Validation\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add Reading')]
#[Layout('layouts.app', ['title' => 'Add Reading'])]
class extends Component {

    public string $date = '';
    public int|string $pv_generated = '';
    public int|string $peak_consumed = '';
    public int|string $off_peak_consumed = '';
    public int|string $peak_fed_in = '';
    public int|string $off_peak_fed_in = '';

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    public function save(): void
    {
        $fields = ['pv_generated', 'peak_consumed', 'off_peak_consumed', 'peak_fed_in', 'off_peak_fed_in'];

        $validated = $this->withValidator(function (Validator $v) use ($fields) {
            $v->after(function (Validator $v) use ($fields) {
                if ($v->errors()->isNotEmpty()) {
                    return;
                }

                $last = Reading::latest('date')->first();

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

                foreach ($fields as $field) {
                    if ((int) $this->$field < $last->$field) {
                        $v->errors()->add($field, __(
                            'Must be at least :min (last reading on :date).',
                            ['min' => $last->$field, 'date' => $last->date->format('d M Y')]
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

        Reading::create($validated);

        Flux::toast(variant: 'success', text: __('Reading saved.'));

        $this->redirect(route('dashboard'), navigate: true);
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
    <livewire:last-reading-card />

<div>
    <flux:heading size="xl" class="mb-1">{{ __('Add Reading') }}</flux:heading>
    <flux:subheading class="mb-6">{{ __('Enter your meter readings for the selected date.') }}</flux:subheading>

    <form wire:submit="save" class="space-y-5">

        <flux:card>
            <flux:input :label="__('Date')" type="text" id="date-picker" required class="pb-6"/>
            <flux:input wire:model="pv_generated" :label="__('PV Generated')" type="number" min="0" required />
        </flux:card>

        <flux:card>
{{--            <flux:heading class="text-center mb-4">{{ __('Consumed') }}</flux:heading>--}}
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
