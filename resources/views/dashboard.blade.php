<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="flex gap-2">
            <flux:button href="{{ route('readings.create') }}" icon="plus">{{ __('Add Reading') }}</flux:button>
            <flux:button href="{{ route('car-charges.create') }}" icon="bolt" wire:navigate>{{ __('Add Car Charge') }}</flux:button>
        </div>
        <div class="portrait:hidden">
            <livewire:monthly-chart />
        </div>
        <flux:card class="landscape:hidden flex items-center gap-3 text-zinc-400">
            <flux:icon name="arrow-path" class="shrink-0 rotate-90" />
            <flux:text>{{ __('Rotate for charts') }}</flux:text>
        </flux:card>
        <div class="grid auto-rows-min gap-4 landscape:grid-cols-2">
            <?php
                $lastDate  = \Carbon\Carbon::parse(\App\Models\Reading::latest('date')->value('date'));
                $firstDate = \Carbon\Carbon::parse(\App\Models\Reading::oldest('date')->value('date'));
                $currentSeason = \App\Models\Season::orderByDesc('starting_date')->first();
            ?>
            @if($currentSeason)
                <livewire:usage-card :label="$currentSeason->name" :season="$currentSeason->id" key="season-current" />
            @endif
            @foreach(range($lastDate->year, $firstDate->year) as $year)
                <?php
                    $startMonth = ($year === $lastDate->year)  ? $lastDate->month : 12;
                    $endMonth   = ($year === $firstDate->year) ? $firstDate->month : 1;
                    $yearDate   = \Carbon\Carbon::create($year, 1, 1);
                ?>
                <livewire:usage-card :label="(string) $year" :year="$yearDate->toDateString()" :key="'year-'.$year" />
                @foreach(range($startMonth, $endMonth) as $month)
                    <?php $monthDate = \Carbon\Carbon::create($year, $month, 1); ?>
                    <livewire:usage-card
                        :label="$monthDate->format('F Y')"
                        :month="$monthDate->toDateString()"
                        :key="'month-'.$year.'-'.$month"
                    />
                @endforeach
            @endforeach
        </div>
    </div>
</x-layouts::app>
