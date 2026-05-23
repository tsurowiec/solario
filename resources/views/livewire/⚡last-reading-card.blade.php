<?php

use App\Models\Reading;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {

    #[Computed]
    public function reading(): ?Reading
    {
        return Reading::latest('date')->first();
    }

}; ?>

@if ($this->reading)
    <?php $r = $this->reading; ?>
    <flux:card>
        <flux:heading class="mb-4">
            {{ __('Last Reading') }}
            <flux:badge class="ml-2" size="sm" variant="outline">{{ $r->date->format('d M Y') }}</flux:badge>
        </flux:heading>

        {{-- PV Generated --}}
        <div class="flex items-center gap-2 mb-4">
            <flux:icon name="sun" class="text-yellow-400 size-8 shrink-0" />
            <flux:text class="font-medium big">{{ number_format($r->pv_generated) }} <span class="text-zinc-400 font-normal text-xs">kWh</span></flux:text>
        </div>

        <div class="grid grid-cols-2 gap-4">
            {{-- Peak --}}
            <div>
                <div class="flex items-center gap-1 mb-2">
                    <flux:icon name="sun" class="text-zinc-400 shrink-0" variant="mini" />
                    <flux:subheading>{{ __('Peak') }}</flux:subheading>
                </div>
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <flux:icon name="arrow-down-circle" class="text-red-400 shrink-0" variant="mini" />
                        <flux:text class="text-sm">{{ number_format($r->peak_consumed) }} <span class="text-zinc-400 text-xs">kWh</span></flux:text>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:icon name="arrow-up-circle" class="text-green-400 shrink-0" variant="mini" />
                        <flux:text class="text-sm">{{ number_format($r->peak_fed_in) }} <span class="text-zinc-400 text-xs">kWh</span></flux:text>
                    </div>
                </div>
            </div>

            {{-- Off-Peak --}}
            <div>
                <div class="flex items-center gap-1 mb-2">
                    <flux:icon name="moon" class="text-zinc-400 shrink-0" variant="mini" />
                    <flux:subheading>{{ __('Off-Peak') }}</flux:subheading>
                </div>
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <flux:icon name="arrow-down-circle" class="text-red-400 shrink-0" variant="mini" />
                        <flux:text class="text-sm">{{ number_format($r->off_peak_consumed) }} <span class="text-zinc-400 text-xs">kWh</span></flux:text>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:icon name="arrow-up-circle" class="text-green-400 shrink-0" variant="mini" />
                        <flux:text class="text-sm">{{ number_format($r->off_peak_fed_in) }} <span class="text-zinc-400 text-xs">kWh</span></flux:text>
                    </div>
                </div>
            </div>
        </div>
    </flux:card>
@else
    <flux:card>
        <flux:heading class="mb-1">{{ __('Last Reading') }}</flux:heading>
        <flux:text>{{ __('No readings yet.') }}</flux:text>
    </flux:card>
@endif
