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

<?php $label = __(':month - Flows', ['month' => Carbon::parse($month)->format('F Y')]); ?>

@if ($this->usage === null)
    <flux:card>
        <flux:heading class="mb-1">{{ $label }}</flux:heading>
        <flux:text>{{ __('No data for this period.') }}</flux:text>
    </flux:card>
@else
<?php
    $d = $this->usage;
    // One row per zone, in time order of the day; with two zones early-peak is empty and left out.
    $zones = $d->hasEarlyPeak
        ? ['early_peak' => ['sunrise', __('Early-Peak')], 'peak' => ['sunset', __('Peak')], 'off_peak' => ['moon', __('Off-Peak')]]
        : ['peak' => ['sun', __('Peak')], 'off_peak' => ['moon', __('Off-Peak')]];
    // One column per field: [label (tooltip), icon, colour].
    $columns = [
        'fedIn' => [__('Fed-in'), 'arrow-up-circle', 'text-green-400'],
        'consumed' => [__('Consumed'), 'arrow-down-circle', 'text-red-400'],
        'fromPreviousMonth' => [__('Minus Prev Month'), 'arrow-uturn-left', 'text-zinc-400'],
        'fromOwn' => [__('Minus Own'), 'arrow-path', 'text-zinc-400'],
        'fromOtherZones' => [__('Minus Other Zones'), 'arrows-right-left', 'text-zinc-400'],
        'toPool' => [__('Moved to Pool'), 'arrow-uturn-right', 'text-zinc-400'],
        'payable' => [__('Payable'), 'banknotes', 'text-blue-400'],
    ];
?>
<flux:card>
    <flux:heading size="lg" class="mb-4">{{ $label }}</flux:heading>

    {{-- Zones as rows, fields as columns (all shown, zeros too); columns only as wide as their content.
         Scrolls sideways when the screen is too narrow. --}}
    <div class="overflow-x-auto">
        <div class="grid grid-cols-[repeat(8,max-content)] justify-start items-center gap-x-4 gap-y-2">
            <div></div>
            @foreach ($columns as [$columnLabel, $columnIcon, $columnColor])
                {{-- Field icon above the unit: skip the value's width (w-10 + gap-1). --}}
                <div class="flex items-center gap-1" title="{{ $columnLabel }}">
                    <span class="w-10 shrink-0"></span>
                    <flux:icon :name="$columnIcon" :class="$columnColor" />
                </div>
            @endforeach

            @foreach ($zones as $zone => [$icon, $name])
                <div title="{{ $name }}"><flux:icon :name="$icon" class="text-blue-400" /></div>
                @foreach (array_keys($columns) as $field)
                    {{-- Same look as <x-stat>: medium value, small grey unit. --}}
                    <flux:text class="flex items-baseline gap-1">
                        <span class="w-10 shrink-0 text-end font-medium tabular-nums">{{ number_format($d->flows[$zone]->$field) }}</span>
                        <span class="text-xs text-zinc-400">kWh</span>
                    </flux:text>
                @endforeach
            @endforeach

            {{-- Credit between months as one more row: calendar icon below the zone icons, credit in under
                 Minus Prev Month (where it is applied), credit out under Moved to Pool (what is left of the pool). --}}
            <flux:separator class="col-span-8 my-2" />
            <div title="{{ __('Credit between months') }}"><flux:icon name="calendar" class="text-blue-400" /></div>
            @foreach (array_keys($columns) as $field)
                @php($credit = ['fromPreviousMonth' => [$d->creditIn, __('Credit from the previous month')], 'toPool' => [$d->creditOut, __('Credit to the next month')]][$field] ?? null)
                @if ($credit)
                    <flux:text class="flex items-baseline gap-1" title="{{ $credit[1] }}">
                        <span class="w-10 shrink-0 text-end font-medium tabular-nums">{{ number_format($credit[0]) }}</span>
                        <span class="text-xs text-zinc-400">kWh</span>
                    </flux:text>
                @else
                    <div></div>
                @endif
            @endforeach
        </div>
    </div>
</flux:card>
@endif
