<?php

use App\Data\EnergySummary;
use App\Services\EnergyUsage;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {

    /** Any date within the month (Y-m-d). */
    public string $month = '';

    #[Computed]
    public function usage(): ?EnergySummary
    {
        return app(EnergyUsage::class)->month(Carbon::parse($this->month));
    }

}; ?>

<?php $label = Carbon::parse($month)->format('F Y'); ?>

@if ($this->usage === null)
    <flux:card>
        <flux:heading class="mb-1">{{ $label }}</flux:heading>
        <flux:text>{{ __('No data for this period.') }}</flux:text>
    </flux:card>
@else
<?php
    $d = $this->usage;
    $from = Carbon::parse($d->from);
    $to = Carbon::parse($d->to);
    $carColors = \App\Models\CarCharge::COLORS;
?>
<flux:card x-data="{ daily: false }">
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="lg">{{ $label }}</flux:heading>
        <div class="flex items-center gap-2">
            <flux:badge size="sm" variant="outline">{{ $from->format('j') }}–{{ $to->format('j M') }}</flux:badge>
            <button type="button" @click="daily = !daily" :class="daily ? 'text-zinc-700 dark:text-zinc-100' : 'text-zinc-400'" class="hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors" title="{{ __('Daily averages') }}">
                <flux:icon name="finger-print" variant="mini" />
            </button>
        </div>
    </div>

    <div x-show="!daily">
        <div class="grid grid-cols-2 gap-2 mb-4">
            <x-stat icon="sun" color="text-yellow-400" :value="number_format($d->pvGenerated)" unit="kWh" />
            <x-stat icon="light-bulb" color="text-blue-400" :value="number_format($d->totalUsage)" unit="kWh" />
        </div>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <x-stat icon="light-bulb" color="text-yellow-400" :value="number_format($d->autoConsumed)" unit="kWh" />
            <x-stat icon="chart-pie" color="text-yellow-400" :value="number_format($d->autoConsumedRatio)" unit="%" />
        </div>
        <div class="grid grid-cols-2 gap-2">
            <x-stat icon="arrow-down-circle" color="text-red-400" :value="number_format($d->consumed)" unit="kWh" />
            <x-stat icon="arrow-up-circle" color="text-green-400" :value="number_format($d->fedIn)" unit="kWh" />
        </div>

        <flux:separator class="my-6" />

        @if ($d->amount !== null)
            <div class="grid grid-cols-2 gap-2 mb-4">
                <x-stat icon="banknotes" color="text-blue-400" :value="number_format($d->amount, 2)" unit="PLN" :note="$d->estimatedAmount !== null ? '~'.number_format($d->estimatedAmount, 2) : null" />
                <x-stat icon="tag" color="text-blue-400" :value="number_format($d->pricePerUnit, 2)" unit="PLN/kWh" />
            </div>
        @endif
        <div class="grid grid-cols-2 gap-2">
            <x-stat icon="sun" color="text-blue-400" :value="number_format($d->peakPayable, 1)" unit="kWh" :note="$d->peakSurplus > 0 ? number_format(-$d->peakSurplus, 1) : null" />
            <x-stat icon="moon" color="text-blue-400" :value="number_format($d->offPeakPayable, 1)" unit="kWh" :note="$d->offPeakSurplus > 0 ? number_format(-$d->offPeakSurplus, 1) : null" />
        </div>

        @foreach ($d->carUsage as $car => $kWh)
            <div class="grid grid-cols-2 gap-2 mt-4">
                <x-stat icon="bolt" :color="$carColors[$car] ?? 'text-zinc-400'" :value="number_format($kWh, 1)" unit="kWh" />
                @if ($d->amount !== null)
                    <x-stat icon="bolt" :color="$carColors[$car] ?? 'text-zinc-400'" :value="number_format($d->carAmounts[$car], 2)" unit="PLN" />
                @endif
            </div>
        @endforeach
        <div class="grid grid-cols-2 gap-2 mt-4">
            <x-stat icon="home" color="text-zinc-400" :value="number_format($d->householdUsage, 1)" unit="kWh" />
            @if ($d->amount !== null)
                <x-stat icon="home" color="text-zinc-400" :value="number_format($d->householdAmount, 2)" unit="PLN" />
            @endif
        </div>
    </div>

    <div x-show="daily" x-cloak>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <x-stat icon="sun" color="text-yellow-400" :value="number_format($d->pvGenerated / $d->days, 1)" unit="kWh/d" />
            <x-stat icon="light-bulb" color="text-blue-400" :value="number_format($d->totalUsage / $d->days, 1)" unit="kWh/d" />
        </div>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <x-stat icon="light-bulb" color="text-yellow-400" :value="number_format($d->autoConsumed / $d->days, 1)" unit="kWh/d" />
            <x-stat icon="chart-pie" color="text-yellow-400" :value="number_format($d->autoConsumedRatio)" unit="%" />
        </div>
        <div class="grid grid-cols-2 gap-2">
            <x-stat icon="arrow-down-circle" color="text-red-400" :value="number_format($d->consumed / $d->days, 1)" unit="kWh/d" />
            <x-stat icon="arrow-up-circle" color="text-green-400" :value="number_format($d->fedIn / $d->days, 1)" unit="kWh/d" />
        </div>

        <flux:separator class="my-6" />

        @if ($d->amount !== null)
            <div class="grid grid-cols-2 gap-2 mb-4">
                <x-stat icon="banknotes" color="text-blue-400" :value="number_format($d->amount / $d->days, 2)" unit="PLN/d" />
                <x-stat icon="tag" color="text-blue-400" :value="number_format($d->pricePerUnit, 2)" unit="PLN/kWh" />
            </div>
        @endif
        <div class="grid grid-cols-2 gap-2">
            <x-stat icon="sun" color="text-blue-400" :value="number_format($d->peakPayablePercent, 1)" unit="%" />
            <x-stat icon="moon" color="text-blue-400" :value="number_format($d->offPeakPayablePercent, 1)" unit="%" />
        </div>

        @foreach ($d->carUsage as $car => $kWh)
            <div class="grid grid-cols-2 gap-2 mt-4">
                <x-stat icon="bolt" :color="$carColors[$car] ?? 'text-zinc-400'" :value="number_format($kWh / $d->days, 1)" unit="kWh/d" />
                @if ($d->amount !== null)
                    <x-stat icon="bolt" :color="$carColors[$car] ?? 'text-zinc-400'" :value="number_format($d->carAmounts[$car] / $d->days, 2)" unit="PLN/d" />
                @endif
            </div>
        @endforeach
        <div class="grid grid-cols-2 gap-2 mt-4">
            <x-stat icon="home" color="text-zinc-400" :value="number_format($d->householdUsage / $d->days, 1)" unit="kWh/d" />
            @if ($d->amount !== null)
                <x-stat icon="home" color="text-zinc-400" :value="number_format($d->householdAmount / $d->days, 2)" unit="PLN/d" />
            @endif
        </div>
    </div>
</flux:card>
@endif
