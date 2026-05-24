<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="flex gap-2">
            <flux:button href="{{ route('readings.create') }}" icon="plus">{{ __('Add Reading') }}</flux:button>
            <flux:button icon="bolt" disabled>{{ __('Tesia') }}</flux:button>
            <flux:button icon="bolt" disabled>{{ __('Tessy') }}</flux:button>
        </div>
        <div class="grid auto-rows-min gap-4 md:grid-cols-2">
            <?php $lastDate = \Carbon\Carbon::parse(\App\Models\Reading::latest('date')->value('date')); ?>
            <livewire:usage-card :label="$lastDate->format('F Y')" :month="$lastDate->toDateString()" />
            <livewire:usage-card :label="$lastDate->copy()->subMonthsNoOverflow()->format('F Y')" :month="$lastDate->copy()->subMonthsNoOverflow()->toDateString()" />
            <livewire:usage-card :label="$lastDate->copy()->subMonthsNoOverflow(2)->format('F Y')" :month="$lastDate->copy()->subMonthsNoOverflow(2)->toDateString()" />
            <livewire:usage-card :label="$lastDate->copy()->subMonthsNoOverflow(3)->format('F Y')" :month="$lastDate->copy()->subMonthsNoOverflow(3)->toDateString()" />
            <livewire:usage-card :label="$lastDate->copy()->subMonthsNoOverflow(4)->format('F Y')" :month="$lastDate->copy()->subMonthsNoOverflow(4)->toDateString()" />
            <livewire:usage-card :label="$lastDate->copy()->subMonthsNoOverflow(5)->format('F Y')" :month="$lastDate->copy()->subMonthsNoOverflow(5)->toDateString()" />
            <livewire:usage-card :label="$lastDate->copy()->subMonthsNoOverflow(6)->format('F Y')" :month="$lastDate->copy()->subMonthsNoOverflow(6)->toDateString()" />
        </div>
    </div>
</x-layouts::app>
