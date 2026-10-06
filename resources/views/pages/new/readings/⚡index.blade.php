<?php

use App\Models\MeterDailyReading;
use App\Models\PvInverterReading;
use App\Services\PvInverterInterpolator;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Readings')]
#[Layout('layouts.app', ['title' => 'Readings'])]
class extends Component {
    use WithPagination;

    private const PER_PAGE = 31;

    public function rendering($view)
    {
        $view->with('days', $this->days());
    }

    /**
     * One row per day (newest first) from the first to the last day with any meter or PV data.
     */
    private function days(): LengthAwarePaginator
    {
        $bounds = collect([
            MeterDailyReading::oldest('date')->value('date'),
            MeterDailyReading::latest('date')->value('date'),
            PvInverterReading::oldest('date')->value('date'),
            PvInverterReading::latest('date')->value('date'),
        ])->filter()->map(fn ($date) => Carbon::parse($date)->startOfDay());

        if ($bounds->isEmpty()) {
            return new LengthAwarePaginator([], 0, self::PER_PAGE);
        }

        $first = $bounds->min();
        $last = $bounds->max();
        $total = (int) $first->diffInDays($last) + 1;
        $page = $this->getPage();

        // Days on this page, newest first.
        $to = $last->copy()->subDays(($page - 1) * self::PER_PAGE);
        $from = $to->copy()->subDays(self::PER_PAGE - 1)->max($first);

        $meter = MeterDailyReading::whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get()
            ->keyBy(fn (MeterDailyReading $r) => $r->date->toDateString());

        $pvReadings = PvInverterReading::whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get()
            ->keyBy(fn (PvInverterReading $r) => $r->date->toDateString());

        $pv = app(PvInverterInterpolator::class)->between($from, $to);

        $rows = [];

        for ($day = $to->copy(); $day->gte($from); $day->subDay()) {
            $date = $day->toDateString();

            $rows[] = [
                'date' => $day->copy(),
                'meter' => $meter->get($date),
                'pvReading' => $pvReadings->get($date),
                'pvValue' => $pv[$date] ?? null,
            ];
        }

        return new LengthAwarePaginator($rows, $total, self::PER_PAGE, $page);
    }

}; ?>

<div class="mx-auto max-w-6xl w-full space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Readings') }}</flux:heading>
        <div class="flex gap-2">
            <flux:button href="{{ route('new.meter-import.create') }}" icon="arrow-up-tray" wire:navigate>{{ __('Import meter data') }}</flux:button>
            <flux:button href="{{ route('new.pv-inverter.create') }}" icon="sun" wire:navigate>{{ __('PV inverter data') }}</flux:button>
        </div>
    </div>

    <flux:card class="overflow-x-auto">
        <flux:table>
            <thead data-flux-columns>
                <tr>
                    <flux:table.column />
                    <flux:table.column colspan="4" align="center" class="border-s border-zinc-800/10 dark:border-white/20">
                        <div class="flex items-center justify-center gap-1"><flux:icon name="sun" variant="mini" class="text-zinc-400" />{{ __('Peak (T1)') }}</div>
                    </flux:table.column>
                    <flux:table.column colspan="4" align="center" class="border-s border-zinc-800/10 dark:border-white/20">
                        <div class="flex items-center justify-center gap-1"><flux:icon name="moon" variant="mini" class="text-zinc-400" />{{ __('Off-Peak (T2)') }}</div>
                    </flux:table.column>
                    <flux:table.column colspan="2" class="border-s border-zinc-800/10 dark:border-white/20" />
                </tr>
                <tr>
                    <flux:table.column />
                    @foreach (['t1', 't2'] as $zone)
                        <flux:table.column colspan="2" align="center" class="border-s border-zinc-800/10 dark:border-white/20">{{ __('Measured') }}</flux:table.column>
                        <flux:table.column colspan="2" align="center" class="border-s border-zinc-800/10 dark:border-white/20">{{ __('Balanced') }}</flux:table.column>
                    @endforeach
                    <flux:table.column colspan="2" class="border-s border-zinc-800/10 dark:border-white/20" />
                </tr>
                <tr>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    @foreach (['t1', 't2'] as $zone)
                        <flux:table.column align="end" class="w-24 min-w-24 border-s border-zinc-800/10 dark:border-white/20">{{ __('Consumed') }}</flux:table.column>
                        <flux:table.column align="end" class="w-24 min-w-24 border-s border-zinc-800/5 dark:border-white/10">{{ __('Fed-in') }}</flux:table.column>
                        <flux:table.column align="end" class="w-24 min-w-24 border-s border-zinc-800/10 dark:border-white/20">{{ __('Consumed') }}</flux:table.column>
                        <flux:table.column align="end" class="w-24 min-w-24 border-s border-zinc-800/5 dark:border-white/10">{{ __('Fed-in') }}</flux:table.column>
                    @endforeach
                    <flux:table.column align="end" class="border-s border-zinc-800/10 dark:border-white/20">{{ __('PV counter') }}</flux:table.column>
                    <flux:table.column class="w-0" />
                </tr>
            </thead>
            <flux:table.rows>
                @foreach ($days as $row)
                    <flux:table.row :key="$row['date']->toDateString()">
                        <flux:table.cell class="whitespace-nowrap">{{ $row['date']->format('D, d M Y') }}</flux:table.cell>
                        @foreach (\App\Models\MeterDailyReading::VALUE_FIELDS as $i => $field)
                            <flux:table.cell align="end" @class(['border-s border-zinc-800/10 dark:border-white/20' => $i % 2 === 0, 'border-s border-zinc-800/5 dark:border-white/10' => $i % 2 === 1])>{{ $row['meter'] ? number_format($row['meter']->$field, 3) : '' }}</flux:table.cell>
                        @endforeach
                        <flux:table.cell align="end" class="border-s border-zinc-800/10 dark:border-white/20">
                            @if ($row['pvReading'])
                                <span class="font-medium">{{ number_format($row['pvReading']->value) }}</span>
                            @elseif ($row['pvValue'] !== null)
                                <span class="italic text-zinc-400" title="{{ __('Interpolated') }}">~{{ number_format($row['pvValue'], 1) }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($row['pvReading'])
                                <flux:button size="xs" icon="pencil" href="{{ route('new.pv-inverter.edit', $row['pvReading']) }}" wire:navigate />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{ $days->links() }}
</div>
