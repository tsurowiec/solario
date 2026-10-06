<?php

use App\Models\Price;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')]
class extends Component {

    /** The prices being edited; null when creating new ones. */
    public ?Price $price = null;

    public string $since = '';

    /** @var array<string, string> */
    public array $values = [];

    public function mount(?Price $price = null): void
    {
        $this->price = $price?->exists ? $price : null;
        $this->since = ($this->price?->since ?? today())->toDateString();

        // New prices start from the latest ones, so only the changed values need editing.
        $source = $this->price ?? Price::latest('since')->first();

        foreach ([...Price::KWH_FIELDS, ...Price::MONTHLY_FIELDS] as $field) {
            $this->values[$field] = $source ? number_format($source->$field, 5, '.', '') : '';
        }
    }

    public function title(): string
    {
        return $this->price ? __('Edit Prices') : __('New Prices');
    }

    public function render()
    {
        return $this->view()->title($this->title());
    }

    public function save(): void
    {
        $amount = ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,5})?$/'];

        $rules = ['since' => ['required', 'date', function ($attribute, $value, $fail) {
            if (Price::whereDate('since', $value)->when($this->price, fn ($q) => $q->whereKeyNot($this->price->id))->exists()) {
                $fail(__('Prices starting on this date already exist.'));
            }
        }]];

        foreach (array_keys($this->values) as $field) {
            $rules['values.'.$field] = $amount;
        }

        $this->validate($rules, [
            'values.*.regex' => __('Use at most 5 decimal places.'),
        ], [
            'values.*' => __('price'),
        ]);

        if ($this->price) {
            $this->price->update(['since' => $this->since, ...$this->values]);
        } else {
            Price::create(['since' => $this->since, ...$this->values]);
        }

        Flux::toast(variant: 'success', text: $this->price ? __('Prices updated.') : __('Prices saved.'));

        $this->redirect(route('new.prices.index'), navigate: true);
    }

}; ?>

@script
<script>
    flatpickr(document.getElementById('date-picker'), {
        dateFormat: 'Y-m-d',
        defaultDate: $wire.since,
        onChange: ([date]) => {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            $wire.since = `${y}-${m}-${d}`;
        },
    });
</script>
@endscript

<?php
    $rows = [
        'sell' => __('Sell'),
        'distr' => __('Distribution'),
        'quality' => __('Quality'),
        'oze' => __('OZE'),
        'cogen' => __('Cogeneration'),
    ];
    $monthly = [
        'sell_monthly' => __('Sell'),
        'power_monthly' => __('Power'),
        'subscription_monthly' => __('Subscription'),
        'network_monthly' => __('Network'),
    ];
?>

<div class="mx-auto max-w-lg w-full space-y-6">
    <div>
        <flux:heading size="xl" class="mb-1">{{ $this->title() }}</flux:heading>
        <flux:subheading class="mb-6">
            {{ $price ? __('Prices in PLN, up to 5 decimal places.') : __('Prices in PLN, up to 5 decimal places. Pre-filled with the latest prices.') }}
        </flux:subheading>

        <form wire:submit="save" class="space-y-5">
            <flux:card>
                <flux:input :label="__('Since')" type="text" id="date-picker" required />
                <flux:error name="since" />
            </flux:card>

            <flux:card class="space-y-4">
                <div class="grid grid-cols-[1fr_7rem_7rem] items-end gap-3">
                    <flux:heading>{{ __('PLN/kWh') }}</flux:heading>
                    <flux:subheading class="flex items-center justify-end gap-1"><flux:icon name="sun" variant="mini" />{{ __('Peak') }}</flux:subheading>
                    <flux:subheading class="flex items-center justify-end gap-1"><flux:icon name="moon" variant="mini" />{{ __('Off-Peak') }}</flux:subheading>
                </div>
                @foreach ($rows as $key => $label)
                    <div class="grid grid-cols-[1fr_7rem_7rem] items-start gap-3">
                        <flux:text class="pt-2">{{ $label }}</flux:text>
                        <flux:input wire:model="values.peak_{{ $key }}" type="number" step="0.00001" min="0" required class:input="text-end" :aria-label="$label.' '.__('Peak')" />
                        <flux:input wire:model="values.off_peak_{{ $key }}" type="number" step="0.00001" min="0" required class:input="text-end" :aria-label="$label.' '.__('Off-Peak')" />
                    </div>
                @endforeach
            </flux:card>

            <flux:card class="space-y-4">
                <flux:heading>{{ __('PLN/month') }}</flux:heading>
                @foreach ($monthly as $field => $label)
                    <div class="grid grid-cols-[1fr_7rem] items-start gap-3">
                        <flux:text class="pt-2">{{ $label }}</flux:text>
                        <flux:input wire:model="values.{{ $field }}" type="number" step="0.00001" min="0" required class:input="text-end" :aria-label="$label" />
                    </div>
                @endforeach
            </flux:card>

            <flux:button variant="primary" type="submit">
                {{ __('Save Prices') }}
            </flux:button>
        </form>
    </div>
</div>
