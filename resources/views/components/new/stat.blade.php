@props(['icon', 'color', 'value', 'unit', 'note' => null])

<div class="flex items-center gap-2">
    <flux:icon :name="$icon" :class="$color . ' shrink-0'" />
    <flux:text class="font-medium">
        {{ $value }} <span class="font-normal text-xs text-zinc-400">{{ $unit }}</span>
        @if ($note !== null)
            <span class="font-normal text-xs text-zinc-400">({{ $note }})</span>
        @endif
    </flux:text>
</div>
