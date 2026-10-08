@props(['price', 'muted' => false, 'badge' => null, 'editable' => true])

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

<flux:card {{ $attributes->class(['opacity-50 grayscale' => $muted]) }}>
    <div class="flex items-center justify-between mb-4">
        <flux:heading size="lg">{{ __('Since :date', ['date' => $price->since->format('d M Y')]) }}</flux:heading>
        <div class="flex items-center gap-2">
            @if ($badge)
                <flux:badge size="sm" variant="outline">{{ $badge }}</flux:badge>
            @endif
            @if ($editable)
                <flux:button size="xs" icon="pencil" href="{{ route('new.prices.edit', $price) }}" wire:navigate :aria-label="__('Edit')" />
                <flux:button variant="danger" size="xs" icon="trash" wire:click="delete({{ $price->id }})" wire:confirm="{{ __('Are you sure you want to delete the prices since :date?', ['date' => $price->since->format('d M Y')]) }}" :aria-label="__('Delete')" />
            @endif
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('PLN/kWh') }}</flux:table.column>
            <flux:table.column align="end" class="w-28">
                <div class="flex items-center justify-end gap-1"><flux:icon name="sun" variant="mini" class="text-zinc-400" />{{ __('Peak') }}</div>
            </flux:table.column>
            <flux:table.column align="end" class="w-28">
                <div class="flex items-center justify-end gap-1"><flux:icon name="moon" variant="mini" class="text-zinc-400" />{{ __('Off-Peak') }}</div>
            </flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($rows as $key => $label)
                <flux:table.row>
                    <flux:table.cell>{{ $label }}</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ number_format($price->{'peak_'.$key}, 5) }}</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ number_format($price->{'off_peak_'.$key}, 5) }}</flux:table.cell>
                </flux:table.row>
            @endforeach
            <flux:table.row>
                <flux:table.cell class="font-semibold">{{ __('Total incl. VAT') }}</flux:table.cell>
                <flux:table.cell align="end" class="font-semibold tabular-nums">~{{ number_format($price->grossPerKwh('peak'), 2) }}</flux:table.cell>
                <flux:table.cell align="end" class="font-semibold tabular-nums">~{{ number_format($price->grossPerKwh('off_peak'), 2) }}</flux:table.cell>
            </flux:table.row>
        </flux:table.rows>
    </flux:table>

    <flux:table class="mt-4">
        <flux:table.columns>
            <flux:table.column>{{ __('PLN/month') }}</flux:table.column>
            <flux:table.column align="end" class="w-28" />
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($monthly as $field => $label)
                <flux:table.row>
                    <flux:table.cell>{{ $label }}</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ number_format($price->$field, 5) }}</flux:table.cell>
                </flux:table.row>
            @endforeach
            <flux:table.row>
                <flux:table.cell class="font-semibold">{{ __('Total incl. VAT') }}</flux:table.cell>
                <flux:table.cell align="end" class="font-semibold tabular-nums">{{ number_format($price->grossMonthly(), 2) }}</flux:table.cell>
            </flux:table.row>
        </flux:table.rows>
    </flux:table>
</flux:card>
