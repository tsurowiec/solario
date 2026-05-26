<?php

use App\Data\UsageSummary;
use App\Models\Season;
use App\Services\CarChargeUsage;
use App\Services\ReadingDiff;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {

    public string $label = '';
    public string $month = '';
    public string $year = '';
    public int $season = 0;

    #[Computed]
    public function usage(): ?UsageSummary
    {
        $diff = app(ReadingDiff::class);

        if ($this->season !== 0) {
            return $diff->season(Season::findOrFail($this->season));
        }

        if ($this->year !== '') {
            return $diff->year(Carbon::parse($this->year));
        }

        return $diff->month(Carbon::parse($this->month));
    }

    #[Computed]
    public function amountByTesia(): float
    {
        $carUsage = app(CarChargeUsage::class);

        if ($this->season !== 0) {
            $kWh = $carUsage->season('tesia', Season::findOrFail($this->season));
        } elseif ($this->year !== '') {
            $kWh = $carUsage->year('tesia', Carbon::parse($this->year));
        } else {
            $kWh = $carUsage->month('tesia', Carbon::parse($this->month));
        }

        return $kWh * $this->usage->pricePerUnit;
    }

    #[Computed]
    public function amountByTessy(): float
    {
        $carUsage = app(CarChargeUsage::class);

        if ($this->season !== 0) {
            $kWh = $carUsage->season('tessy', Season::findOrFail($this->season));
        } elseif ($this->year !== '') {
            $kWh = $carUsage->year('tessy', Carbon::parse($this->year));
        } else {
            $kWh = $carUsage->month('tessy', Carbon::parse($this->month));
        }

        return $kWh * $this->usage->pricePerUnit;
    }

    #[Computed]
    public function amountByHouse(): float
    {
        return $this->usage->amount - $this->amountByTesia - $this->amountByTessy;
    }

    #[Computed]
    public function kWhByTesia(): float
    {
        $carUsage = app(CarChargeUsage::class);

        if ($this->season !== 0) {
            return $carUsage->season('tesia', Season::findOrFail($this->season));
        } elseif ($this->year !== '') {
            return $carUsage->year('tesia', Carbon::parse($this->year));
        }

        return $carUsage->month('tesia', Carbon::parse($this->month));
    }

    #[Computed]
    public function kWhByTessy(): float
    {
        $carUsage = app(CarChargeUsage::class);

        if ($this->season !== 0) {
            return $carUsage->season('tessy', Season::findOrFail($this->season));
        } elseif ($this->year !== '') {
            return $carUsage->year('tessy', Carbon::parse($this->year));
        }

        return $carUsage->month('tessy', Carbon::parse($this->month));
    }

    #[Computed]
    public function kWhByHouse(): float
    {
        return $this->usage->totalUsage - $this->kWhByTesia - $this->kWhByTessy;
    }

}; ?>

@if ($this->usage === null)
    <flux:card>
        <flux:heading class="mb-1">{{ __($label) }}</flux:heading>
        <flux:text>{{ __('No data for this period.') }}</flux:text>
    </flux:card>
@else
<?php
    $d = $this->usage;
    $days = max(1, Carbon::parse($d->from)->diffInDays(Carbon::parse($d->to)));
?>
<flux:card x-data="{ daily: false }">
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="lg">
            {{ __($label) }}
        </flux:heading>
        <button type="button" @click="daily = !daily" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors">
            <flux:icon name="finger-print" variant="mini" />
        </button>
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
        <div class="grid grid-cols-2 gap-2 mb-6">
            <x-stat icon="arrow-down-circle" color="text-red-400" :value="number_format($d->consumed)" unit="kWh" />
            <x-stat icon="arrow-up-circle" color="text-green-400" :value="number_format($d->fedIn)" unit="kWh" />
        </div>
    </div>

    <div x-show="daily" x-cloak>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <x-stat icon="sun" color="text-yellow-400" :value="number_format($d->pvGenerated / $days, 1)" unit="kWh/d" />
            <x-stat icon="light-bulb" color="text-blue-400" :value="number_format($d->totalUsage / $days, 1)" unit="kWh/d" />
        </div>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <x-stat icon="light-bulb" color="text-yellow-400" :value="number_format($d->autoConsumed / $days, 1)" unit="kWh/d" />
            <x-stat icon="chart-pie" color="text-yellow-400" :value="number_format($d->autoConsumedRatio)" unit="%" />
        </div>
        <div class="grid grid-cols-2 gap-2 mb-6">
            <x-stat icon="arrow-down-circle" color="text-red-400" :value="number_format($d->consumed / $days, 1)" unit="kWh/d" />
            <x-stat icon="arrow-up-circle" color="text-green-400" :value="number_format($d->fedIn / $days, 1)" unit="kWh/d" />
        </div>
    </div>

    <flux:separator class="my-6" />

    <div x-show="!daily">
        <div class="grid grid-cols-2 gap-2 mb-4">
            <x-stat icon="banknotes" color="text-blue-400" :value="number_format($d->amount, 2)" unit="PLN" />
            <x-stat icon="tag" color="text-blue-400" :value="number_format($d->pricePerUnit, 2)" unit="PLN/kWh" />
        </div>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <x-stat icon="sun" color="text-blue-400" :value="number_format($d->peakPayable, 1)" unit="kWh" />
            <x-stat icon="moon" color="text-blue-400" :value="number_format($d->offPeakPayable, 1)" unit="kWh" />
        </div>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <x-stat icon="bolt" color="text-zinc-400" :value="number_format($this->kWhByTesia, 1)" unit="kWh" />
            <x-stat icon="bolt" color="text-zinc-400" :value="number_format($this->amountByTesia, 2)" unit="PLN" />
        </div>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <x-stat icon="bolt" color="text-red-400" :value="number_format($this->kWhByTessy, 1)" unit="kWh" />
            <x-stat icon="bolt" color="text-red-400" :value="number_format($this->amountByTessy, 2)" unit="PLN" />
        </div>
        <div class="grid grid-cols-2 gap-2">
            <x-stat icon="home" color="text-zinc-400" :value="number_format($this->kWhByHouse, 1)" unit="kWh" />
            <x-stat icon="home" color="text-zinc-400" :value="number_format($this->amountByHouse, 2)" unit="PLN" />
        </div>
    </div>
    <div x-show="daily" x-cloak>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <x-stat icon="banknotes" color="text-blue-400" :value="number_format($d->peakPayable + $d->offPeakPayable, 1)" unit="kWh" />
            <x-stat icon="tag" color="text-blue-400" :value="number_format($d->pricePerUnit, 2)" unit="PLN/kWh" />
        </div>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <x-stat icon="sun" color="text-blue-400" :value="number_format($d->peakPayableRatio * 100, 1)" unit="%" />
            <x-stat icon="moon" color="text-blue-400" :value="number_format($d->offPeakPayableRatio * 100, 1)" unit="%" />
        </div>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <x-stat icon="bolt" color="text-zinc-400" :value="number_format($this->kWhByTesia / $days, 1)" unit="kWh/d" />
            <x-stat icon="bolt" color="text-zinc-400" :value="number_format($this->amountByTesia / $days, 2)" unit="PLN/d" />
        </div>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <x-stat icon="bolt" color="text-red-400" :value="number_format($this->kWhByTessy / $days, 1)" unit="kWh/d" />
            <x-stat icon="bolt" color="text-red-400" :value="number_format($this->amountByTessy / $days, 2)" unit="PLN/d" />
        </div>
        <div class="grid grid-cols-2 gap-2">
            <x-stat icon="home" color="text-zinc-400" :value="number_format($this->kWhByHouse / $days, 1)" unit="kWh/d" />
            <x-stat icon="home" color="text-zinc-400" :value="number_format($this->amountByHouse / $days, 2)" unit="PLN/d" />
        </div>
    </div>
</flux:card>
@endif
