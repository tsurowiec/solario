<?php

use App\Data\UsageSummary;
use App\Services\PriceCalculator;
use App\Services\ReadingDiff;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {

    public string $label = '';
    public string $month = '';

    #[Computed]
    public function usage(): ?UsageSummary
    {
        return app(ReadingDiff::class)->month(Carbon::parse($this->month));
    }

    #[Computed]
    public function price(): ?float
    {
        if ($this->usage === null) {
            return null;
        }

        return app(PriceCalculator::class)->calculate($this->usage);
    }

}; ?>

@if ($this->usage === null)
    <flux:card>
        <flux:heading class="mb-1">{{ __($label) }}</flux:heading>
        <flux:text>{{ __('No data for this period.') }}</flux:text>
    </flux:card>
@else
<?php $d = $this->usage; ?>
<flux:card>
    <flux:heading size="lg" class="mb-4">
        {{ __($label) }}
    </flux:heading>

    {{-- Summary row --}}
    <div class="grid grid-cols-3 gap-2 mb-2">
        <div class="flex items-center gap-2">
            <flux:icon name="sun" class="text-yellow-400 shrink-0" />
            <flux:text class="font-medium">{{ number_format($d->pvGenerated) }} <span class="text-zinc-400 font-normal text-xs">kWh</span></flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:icon name="light-bulb" class="text-yellow-400 shrink-0" />
            <flux:text class="font-medium">{{ number_format($d->autoConsumed) }} <span class="text-zinc-400 font-normal text-xs">kWh</span></flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:icon name="chart-pie" class="text-yellow-400 shrink-0" />
            <flux:text class="font-medium">{{ number_format($d->autoConsumedRatio) }} <span class="text-zinc-400 font-normal text-xs">%</span></flux:text>
        </div>
    </div>
    <div class="grid grid-cols-3 gap-2 mb-4">
        <div class="flex items-center gap-2">
            <flux:icon name="light-bulb" class="text-blue-400 shrink-0" />
            <flux:text class="font-medium">{{ number_format($d->totalUsage) }} <span class="text-zinc-400 font-normal text-xs">kWh</span></flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:icon name="arrow-down-circle" class="text-red-400 shrink-0" />
            <flux:text class="font-medium">{{ number_format($d->consumed) }} <span class="text-zinc-400 font-normal text-xs">kWh</span></flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:icon name="arrow-up-circle" class="text-green-400 shrink-0" />
            <flux:text class="font-medium">{{ number_format($d->fedIn) }} <span class="text-zinc-400 font-normal text-xs">kWh</span></flux:text>
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

    @if ($this->price !== null)
    <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700 flex items-center gap-2">
        <flux:icon name="banknotes" class="shrink-0 {{ $this->price >= 0 ? 'text-blue-400' : 'text-green-400' }}" />
        <flux:text class="font-semibold text-base">
            {{ number_format($this->price, 2) }} <span class="font-normal text-xs text-zinc-400">PLN</span>
        </flux:text>
    </div>
    @endif
</flux:card>
@endif
