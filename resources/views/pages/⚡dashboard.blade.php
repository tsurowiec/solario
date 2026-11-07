<?php

use App\Models\MeterDailyReading;
use App\Models\PvInverterReading;
use App\Services\PvInverterInterpolator;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')]
#[Layout('layouts.app', ['title' => 'Dashboard'])]
class extends Component {

    /**
     * The month of the latest day that can have both meter and PV inverter data,
     * but never before the first meter day (PV readings may start earlier).
     * With two or more PV readings the PV counter is extrapolated, so the last meter day counts.
     */
    #[Computed]
    public function month(): ?string
    {
        $firstMeter = MeterDailyReading::oldest('date')->value('date');
        $lastMeter = MeterDailyReading::latest('date')->value('date');
        $lastPv = PvInverterReading::latest('date')->value('date');

        if (! $lastMeter) {
            return null;
        }

        $latest = $lastPv && ! app(PvInverterInterpolator::class)->extrapolates()
            ? max($firstMeter, min($lastMeter, $lastPv))
            : $lastMeter;

        return Carbon::parse($latest)->startOfMonth()->toDateString();
    }

    /**
     * Every month from the first meter day's month up to the latest month, newest first.
     *
     * @return list<string>
     */
    #[Computed]
    public function months(): array
    {
        if (! $this->month) {
            return [];
        }

        $first = Carbon::parse(MeterDailyReading::oldest('date')->value('date'))->startOfMonth();
        $months = [];

        for ($month = Carbon::parse($this->month); $month->gte($first); $month->subMonth()) {
            $months[] = $month->toDateString();
        }

        return $months;
    }

}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div class="flex flex-wrap gap-2">
        <flux:button href="{{ route('readings.index') }}" icon="list-bullet" wire:navigate>{{ __('Readings') }}</flux:button>
        <flux:button href="{{ route('car-charges.index') }}" icon="bolt" wire:navigate>{{ __('Car Charges') }}</flux:button>
    </div>

    @if ($this->month)
        {{-- Landscape only, like the charts; it is too wide for portrait. --}}
        <div class="portrait:hidden flex flex-col gap-4">
            <livewire:month-flows :month="$this->month" :key="'flows-'.$this->month" />
            <livewire:monthly-chart :month="$this->month" :key="'chart-'.$this->month" />
            <livewire:energy-split-chart :month="$this->month" :key="'split-'.$this->month" />
        </div>
        <flux:card class="landscape:hidden flex items-center gap-3 text-zinc-400">
            <flux:icon name="arrow-path" class="shrink-0 rotate-90" />
            <flux:text>{{ __('Rotate for flows and charts') }}</flux:text>
        </flux:card>

        <div class="grid auto-rows-min gap-4 landscape:grid-cols-2">
            @foreach ($this->months as $month)
                <livewire:month-card :month="$month" :key="'month-'.$month" />
            @endforeach
        </div>
    @endif
</div>
