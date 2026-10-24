    <tr>
        <flux:table.column />
        <flux:table.column colspan="2" align="center" class="w-0 border-s border-zinc-800/10 dark:border-white/20">
            <div class="flex items-center justify-center gap-1 whitespace-normal text-center"><flux:icon name="sun" variant="mini" class="shrink-0 text-yellow-400" />{{ __('Inverter') }}</div>
        </flux:table.column>
        <flux:table.column colspan="4" align="center" class="border-s border-zinc-800/10 dark:border-white/20">
            <div class="flex items-center justify-center gap-1"><flux:icon name="sun" variant="mini" class="text-zinc-400" />{{ __('Peak (T1)') }}</div>
        </flux:table.column>
        <flux:table.column colspan="4" align="center" class="border-s border-zinc-800/10 dark:border-white/20">
            <div class="flex items-center justify-center gap-1"><flux:icon name="moon" variant="mini" class="text-zinc-400" />{{ __('Off-Peak (T2)') }}</div>
        </flux:table.column>
    </tr>
    <tr>
        <flux:table.column />
        <flux:table.column class="border-s border-zinc-800/10 dark:border-white/20" />
        <flux:table.column class="w-5 px-0" />
        @foreach (['t1', 't2'] as $zone)
            <flux:table.column colspan="2" align="center" class="border-s border-zinc-800/10 dark:border-white/20">{{ __('Measured') }}</flux:table.column>
            <flux:table.column colspan="2" align="center" class="border-s border-zinc-800/10 dark:border-white/20">{{ __('Balanced') }}</flux:table.column>
        @endforeach
    </tr>
    <tr>
        <flux:table.column>{{ __('Date') }}</flux:table.column>
        <flux:table.column class="border-s border-zinc-800/10 dark:border-white/20" />
        <flux:table.column class="w-5 px-0" />
        @foreach (['t1', 't2'] as $zone)
            <flux:table.column align="end" class="border-s border-zinc-800/10 dark:border-white/20"><div class="flex justify-end" title="{{ __('Consumed') }}"><flux:icon name="arrow-down-circle" variant="mini" class="text-red-400" /><span class="sr-only">{{ __('Consumed') }}</span></div></flux:table.column>
            <flux:table.column align="end" class="border-s border-zinc-800/5 dark:border-white/10"><div class="flex justify-end" title="{{ __('Fed-in') }}"><flux:icon name="arrow-up-circle" variant="mini" class="text-green-400" /><span class="sr-only">{{ __('Fed-in') }}</span></div></flux:table.column>
            <flux:table.column align="end" class="border-s border-zinc-800/10 dark:border-white/20"><div class="flex justify-end" title="{{ __('Consumed') }}"><flux:icon name="arrow-down-circle" variant="mini" class="text-red-400" /><span class="sr-only">{{ __('Consumed') }}</span></div></flux:table.column>
            <flux:table.column align="end" class="border-s border-zinc-800/5 dark:border-white/10"><div class="flex justify-end" title="{{ __('Fed-in') }}"><flux:icon name="arrow-up-circle" variant="mini" class="text-green-400" /><span class="sr-only">{{ __('Fed-in') }}</span></div></flux:table.column>
        @endforeach
    </tr>
