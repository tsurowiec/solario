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

    {{-- Summary rows: totals --}}
    <div x-show="!daily">
        <div class="grid grid-cols-2 gap-2 mb-4">
            <div class="flex items-center gap-2">
                <flux:icon name="sun" class="text-yellow-400 shrink-0" />
                <flux:text class="font-medium">{{ number_format($d->pvGenerated) }} <span class="text-zinc-400 font-normal text-xs">kWh</span></flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:icon name="light-bulb" class="text-blue-400 shrink-0" />
                <flux:text class="font-medium">{{ number_format($d->totalUsage) }} <span class="text-zinc-400 font-normal text-xs">kWh</span></flux:text>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <div class="flex items-center gap-2">
                <flux:icon name="light-bulb" class="text-yellow-400 shrink-0" />
                <flux:text class="font-medium">{{ number_format($d->autoConsumed) }} <span class="text-zinc-400 font-normal text-xs">kWh</span></flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:icon name="chart-pie" class="text-yellow-400 shrink-0" />
                <flux:text class="font-medium">{{ number_format($d->autoConsumedRatio) }} <span class="text-zinc-400 font-normal text-xs">%</span></flux:text>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2 mb-6">
            <div class="flex items-center gap-2">
                <flux:icon name="arrow-down-circle" class="text-red-400 shrink-0" />
                <flux:text class="font-medium">{{ number_format($d->consumed) }} <span class="text-zinc-400 font-normal text-xs">kWh</span></flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:icon name="arrow-up-circle" class="text-green-400 shrink-0" />
                <flux:text class="font-medium">{{ number_format($d->fedIn) }} <span class="text-zinc-400 font-normal text-xs">kWh</span></flux:text>
            </div>
        </div>
    </div>

    {{-- Summary rows: daily averages --}}
    <div x-show="daily" x-cloak>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <div class="flex items-center gap-2">
                <flux:icon name="sun" class="text-yellow-400 shrink-0" />
                <flux:text class="font-medium">{{ number_format($d->pvGenerated / $days, 1) }} <span class="text-zinc-400 font-normal text-xs">kWh/d</span></flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:icon name="light-bulb" class="text-blue-400 shrink-0" />
                <flux:text class="font-medium">{{ number_format($d->totalUsage / $days, 1) }} <span class="text-zinc-400 font-normal text-xs">kWh/d</span></flux:text>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <div class="flex items-center gap-2">
                <flux:icon name="light-bulb" class="text-yellow-400 shrink-0" />
                <flux:text class="font-medium">{{ number_format($d->autoConsumed / $days, 1) }} <span class="text-zinc-400 font-normal text-xs">kWh/d</span></flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:icon name="chart-pie" class="text-yellow-400 shrink-0" />
                <flux:text class="font-medium">{{ number_format($d->autoConsumedRatio) }} <span class="text-zinc-400 font-normal text-xs">%</span></flux:text>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2 mb-6">
            <div class="flex items-center gap-2">
                <flux:icon name="arrow-down-circle" class="text-red-400 shrink-0" />
                <flux:text class="font-medium">{{ number_format($d->consumed / $days, 1) }} <span class="text-zinc-400 font-normal text-xs">kWh/d</span></flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:icon name="arrow-up-circle" class="text-green-400 shrink-0" />
                <flux:text class="font-medium">{{ number_format($d->fedIn / $days, 1) }} <span class="text-zinc-400 font-normal text-xs">kWh/d</span></flux:text>
            </div>
        </div>
    </div>

{{--    <div class="grid grid-cols-2 gap-4">--}}
{{--        --}}{{-- Peak --}}
{{--        <div>--}}
{{--            <div class="flex items-center gap-1 mb-2">--}}
{{--                <flux:icon name="sun" class="text-zinc-400 shrink-0" variant="mini" />--}}
{{--                <flux:subheading>{{ __('Peak') }}</flux:subheading>--}}
{{--            </div>--}}
{{--            <div class="space-y-1">--}}
{{--                <div class="flex items-center gap-2">--}}
{{--                    <flux:icon name="arrow-down-circle" class="text-red-400 shrink-0" variant="mini" />--}}
{{--                    <flux:text class="text-sm">{{ number_format($d['peak_consumed']) }} <span class="text-zinc-400 text-xs">kWh</span></flux:text>--}}
{{--                </div>--}}
{{--                <div class="flex items-center gap-2">--}}
{{--                    <flux:icon name="arrow-up-circle" class="text-green-400 shrink-0" variant="mini" />--}}
{{--                    <flux:text class="text-sm">{{ number_format($d['peak_fed_in']) }} <span class="text-zinc-400 text-xs">kWh</span></flux:text>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}

{{--        --}}{{-- Off-Peak --}}
{{--        <div>--}}
{{--            <div class="flex items-center gap-1 mb-2">--}}
{{--                <flux:icon name="moon" class="text-zinc-400 shrink-0" variant="mini" />--}}
{{--                <flux:subheading>{{ __('Off-Peak') }}</flux:subheading>--}}
{{--            </div>--}}
{{--            <div class="space-y-1">--}}
{{--                <div class="flex items-center gap-2">--}}
{{--                    <flux:icon name="arrow-down-circle" class="text-red-400 shrink-0" variant="mini" />--}}
{{--                    <flux:text class="text-sm">{{ number_format($d['off_peak_consumed']) }} <span class="text-zinc-400 text-xs">kWh</span></flux:text>--}}
{{--                </div>--}}
{{--                <div class="flex items-center gap-2">--}}
{{--                    <flux:icon name="arrow-up-circle" class="text-green-400 shrink-0" variant="mini" />--}}
{{--                    <flux:text class="text-sm">{{ number_format($d['off_peak_fed_in']) }} <span class="text-zinc-400 text-xs">kWh</span></flux:text>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </div>--}}

    <div class="mt-6 pt-4 border-t border-zinc-200 dark:border-zinc-700">
        <div x-show="!daily" class="grid grid-cols-2 gap-2 mb-4">
            <div class="flex items-center gap-2">
                <flux:icon name="banknotes" class="shrink-0 text-blue-400" />
                <flux:text class="font-medium">{{ number_format($this->usage->amount, 2) }} <span class="font-normal text-xs text-zinc-400">PLN</span></flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:icon name="tag" class="shrink-0 text-blue-400" />
                <flux:text class="font-medium">{{ number_format($this->usage->pricePerUnit, 2) }} <span class="font-normal text-xs text-zinc-400">PLN/kWh</span></flux:text>
            </div>
        </div>
        <div x-show="daily" x-cloak class="grid grid-cols-2 gap-2 mb-4">
            <div class="flex items-center gap-2">
                <flux:icon name="banknotes" class="shrink-0 text-blue-400" />
                <flux:text class="font-medium">{{ number_format($this->usage->peakPayable + $this->usage->offPeakPayable, 1) }} <span class="font-normal text-xs text-zinc-400">kWh</span></flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:icon name="tag" class="shrink-0 text-blue-400" />
                <flux:text class="font-medium">{{ number_format($this->usage->pricePerUnit, 2) }} <span class="font-normal text-xs text-zinc-400">PLN/kWh</span></flux:text>
            </div>
        </div>
        <div x-show="!daily" class="grid grid-cols-2 gap-2 mb-4">
            <div class="flex items-center gap-2">
                <flux:icon name="sun" class="shrink-0 text-blue-400" />
                <flux:text class="font-medium">{{ number_format($this->usage->peakPayable, 1) }} <span class="font-normal text-xs text-zinc-400">kWh</span></flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:icon name="moon" class="shrink-0 text-blue-400" />
                <flux:text class="font-medium">{{ number_format($this->usage->offPeakPayable, 1) }} <span class="font-normal text-xs text-zinc-400">kWh</span></flux:text>
            </div>
        </div>
        <div x-show="daily" x-cloak class="grid grid-cols-2 gap-2 mb-4">
            <div class="flex items-center gap-2">
                <flux:icon name="sun" class="shrink-0 text-blue-400" />
                <flux:text class="font-medium">{{ number_format($this->usage->peakPayableRatio * 100, 1) }} <span class="font-normal text-xs text-zinc-400">%</span></flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:icon name="moon" class="shrink-0 text-blue-400" />
                <flux:text class="font-medium">{{ number_format($this->usage->offPeakPayableRatio * 100, 1) }} <span class="font-normal text-xs text-zinc-400">%</span></flux:text>
            </div>
        </div>
        <div x-show="!daily" class="grid grid-cols-2 gap-2 mb-4">
            <div class="flex items-center gap-2">
                <flux:icon name="bolt" class="shrink-0 text-zinc-400" />
                <flux:text class="font-medium">{{ number_format($this->amountByTesia, 2) }} <span class="font-normal text-xs text-zinc-400">PLN</span></flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:icon name="bolt" class="shrink-0 text-red-400" />
                <flux:text class="font-medium">{{ number_format($this->amountByTessy, 2) }} <span class="font-normal text-xs text-zinc-400">PLN</span></flux:text>
            </div>
        </div>
        <div x-show="!daily" class="grid grid-cols-2 gap-2">
            <div class="flex items-center gap-2">
                <flux:icon name="home" class="shrink-0 text-zinc-400" />
                <flux:text class="font-medium">{{ number_format($this->amountByHouse, 2) }} <span class="font-normal text-xs text-zinc-400">PLN</span></flux:text>
            </div>
        </div>
        <div x-show="daily" x-cloak class="grid grid-cols-2 gap-2 mb-4">
            <div class="flex items-center gap-2">
                <flux:icon name="bolt" class="shrink-0 text-zinc-400" />
                <flux:text class="font-medium">{{ number_format($this->kWhByTesia, 1) }} <span class="font-normal text-xs text-zinc-400">kWh</span></flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:icon name="bolt" class="shrink-0 text-red-400" />
                <flux:text class="font-medium">{{ number_format($this->kWhByTessy, 1) }} <span class="font-normal text-xs text-zinc-400">kWh</span></flux:text>
            </div>
        </div>
        <div x-show="daily" x-cloak class="grid grid-cols-2 gap-2">
            <div class="flex items-center gap-2">
                <flux:icon name="home" class="shrink-0 text-zinc-400" />
                <flux:text class="font-medium">{{ number_format($this->kWhByHouse, 1) }} <span class="font-normal text-xs text-zinc-400">kWh</span></flux:text>
            </div>
        </div>
    </div>
</flux:card>
@endif
