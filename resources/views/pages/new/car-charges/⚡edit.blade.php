<?php

use App\Models\CarCharge;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Car Charge')]
#[Layout('layouts.app', ['title' => 'Edit Car Charge'])]
class extends Component {

    public CarCharge $charge;

    public string $date = '';
    public string $car_id = '';
    public int|string $charged = '';

    public function mount(CarCharge $charge): void
    {
        $this->charge = $charge;
        $this->date = $charge->date->toDateString();
        $this->car_id = $charge->car_id;
        $this->charged = $charge->charged;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'date'    => ['required', 'date'],
            'car_id'  => ['required', 'in:'.implode(',', CarCharge::CARS)],
            'charged' => ['required', 'integer', 'min:0'],
        ]);

        $this->charge->update($validated);

        Flux::toast(variant: 'success', text: __('Charge updated.'));

        $this->redirect(route('new.car-charges.index', [
            'car' => $this->charge->car_id,
            'month' => $this->charge->date->format('Y-m'),
        ]), navigate: true);
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
        <flux:heading size="xl" class="mb-1">{{ __('Edit Car Charge') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Update the energy charged for a car.') }}</flux:subheading>

        <form wire:submit="save" class="space-y-5">
            <flux:card>
                <flux:input :label="__('Date')" type="text" id="date-picker" required />
                <flux:error name="date" />
            </flux:card>

            <flux:card>
                <flux:select wire:model="car_id" :label="__('Car')">
                    @foreach (\App\Models\CarCharge::CARS as $car)
                        <flux:select.option :value="$car">{{ ucfirst($car) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:card>

            <flux:card>
                <flux:input wire:model="charged" :label="__('Charged (kWh)')" type="number" min="0" required />
            </flux:card>

            <flux:button variant="primary" type="submit">
                {{ __('Save Charge') }}
            </flux:button>
        </form>
    </div>
</div>
