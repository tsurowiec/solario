@props(['icon' => null, 'color' => '', 'value', 'unit', 'note' => null])

{{-- Icon, value, unit in narrow columns: the value has a fixed width and is right-aligned,
     so values and units line up down a card while staying next to their icon. --}}
<div class="flex items-center gap-2">
    {{-- Without an icon its space is kept, so the value still lines up with stats that have one. --}}
    @if ($icon)
        <flux:icon :name="$icon" :class="$color . ' shrink-0'" />
    @else
        <span class="size-6 shrink-0"></span>
    @endif
    <flux:text class="flex items-baseline gap-1">
        <span class="w-14 shrink-0 text-end font-medium tabular-nums">{{ $value }}</span>
        <span class="text-xs text-zinc-400">{{ $unit }}</span>
        @if ($note !== null)
            <span class="text-xs text-zinc-400">({{ $note }})</span>
        @endif
    </flux:text>
</div>
