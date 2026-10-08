<?php

use App\Models\MeterDailyReading;
use App\Models\PvInverterReading;
use App\Services\PvInverterInterpolator;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Readings')]
#[Layout('layouts.app', ['title' => 'Readings'])]
class extends Component {

    /** Selected month (Y-m); the current month by default. */
    #[Url]
    public string $month = '';

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = today()->format('Y-m');
        }
    }

    /**
     * Months to choose from (Y-m => label), newest first: every month with meter or PV data, plus the current and selected month.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function months(): array
    {
        return MeterDailyReading::pluck('date')
            ->merge(PvInverterReading::pluck('date'))
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m'))
            ->push(today()->format('Y-m'), $this->month)
            ->unique()
            ->sortDesc()
            ->mapWithKeys(fn (string $ym) => [$ym => Carbon::createFromFormat('!Y-m', $ym)->format('F Y')])
            ->all();
    }

    /**
     * One row per day of the selected month (newest first), limited to the range
     * between the first and the last day with any meter or PV data.
     *
     * @return list<array{date: Carbon, meter: ?MeterDailyReading, pvReading: ?PvInverterReading, pvValue: ?float}>
     */
    #[Computed]
    public function days(): array
    {
        $bounds = collect([
            MeterDailyReading::oldest('date')->value('date'),
            MeterDailyReading::latest('date')->value('date'),
            PvInverterReading::oldest('date')->value('date'),
            PvInverterReading::latest('date')->value('date'),
        ])->filter()->map(fn ($date) => Carbon::parse($date)->startOfDay());

        if ($bounds->isEmpty()) {
            return [];
        }

        $start = Carbon::createFromFormat('!Y-m', $this->month);
        $from = $start->copy()->max($bounds->min());
        $to = $start->copy()->endOfMonth()->startOfDay()->min($bounds->max());

        if ($from->gt($to)) {
            return [];
        }

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

        return $rows;
    }

    /**
     * Sum of each meter field over the days of the selected month that have meter data.
     *
     * @return array<string, float>
     */
    #[Computed]
    public function totals(): array
    {
        $meter = collect($this->days)->pluck('meter')->filter();

        return collect(MeterDailyReading::VALUE_FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => (float) $meter->sum($field)])
            ->all();
    }

    /**
     * PV produced over the listed days: counter on the last day − counter on the day before the first.
     * Exact only when both counters are actual readings; null when either cannot be determined.
     *
     * @return array{value: float, exact: bool}|null
     */
    #[Computed]
    public function pvProduction(): ?array
    {
        $days = $this->days;

        if (! $days) {
            return null;
        }

        $to = $days[0]['date'];
        $before = $days[count($days) - 1]['date']->copy()->subDay();

        $pv = app(PvInverterInterpolator::class)->between($before, $to);

        if (! isset($pv[$before->toDateString()], $pv[$to->toDateString()])) {
            return null;
        }

        $exact = PvInverterReading::whereDate('date', $before->toDateString())->exists()
            && PvInverterReading::whereDate('date', $to->toDateString())->exists();

        return [
            'value' => $pv[$to->toDateString()] - $pv[$before->toDateString()],
            'exact' => $exact,
        ];
    }

}; ?>

<div class="mx-auto max-w-6xl w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('Readings') }}</flux:heading>
        <div class="flex flex-wrap gap-2">
            <flux:button href="{{ route('new.meter-import.create') }}" icon="arrow-up-tray" wire:navigate>{{ __('Import meter data') }}</flux:button>
            <flux:button href="{{ route('new.pv-inverter.create') }}" icon="sun" wire:navigate>{{ __('PV inverter data') }}</flux:button>
        </div>
    </div>

    <div class="flex justify-end">
        <flux:select wire:model.live="month" class="max-w-48" :aria-label="__('Month')">
            @foreach ($this->months as $value => $label)
                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:card class="overflow-x-auto">
        <flux:table>
            <colgroup>
                <col span="2" />
                <col class="w-5" />
                <col span="8" />
            </colgroup>
            <thead data-flux-columns>
                <tr>
                    <flux:table.column />
                    <flux:table.column colspan="2" align="end" class="w-0 border-s border-zinc-800/10 dark:border-white/20">
                        <div class="flex items-center justify-end gap-1 whitespace-normal text-end"><flux:icon name="sun" variant="mini" class="shrink-0 text-yellow-400" />{{ __('PV Production') }}</div>
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
            </thead>
            <flux:table.rows>
                @foreach ($this->days as $row)
                    <flux:table.row :key="$row['date']->toDateString()">
                        <flux:table.cell class="whitespace-nowrap">{{ $row['date']->format('D, d M Y') }}</flux:table.cell>
                        <flux:table.cell align="end" class="border-s border-zinc-800/10 dark:border-white/20">
                            @if ($row['pvReading'])
                                <span class="font-medium">{{ number_format($row['pvReading']->value) }}</span>
                            @elseif ($row['pvValue'] !== null)
                                <span class="italic text-zinc-400" title="{{ __('Interpolated') }}">~{{ number_format($row['pvValue'], 1) }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="w-5 px-0">
                            @if ($row['pvReading'])
                                <flux:button size="xs" variant="ghost" icon="pencil" class="-my-1 size-5!" href="{{ route('new.pv-inverter.edit', $row['pvReading']) }}" wire:navigate />
                            @endif
                        </flux:table.cell>
                        @foreach (\App\Models\MeterDailyReading::VALUE_FIELDS as $i => $field)
                            <flux:table.cell align="end" @class(['border-s border-zinc-800/10 dark:border-white/20' => $i % 2 === 0, 'border-s border-zinc-800/5 dark:border-white/10' => $i % 2 === 1])>{{ $row['meter'] ? number_format($row['meter']->$field, 3) : '' }}</flux:table.cell>
                        @endforeach
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
            @if ($this->days)
                <flux:table.rows>
                    <flux:table.row>
                        <flux:table.cell class="font-semibold">{{ __('Total') }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-semibold border-s border-zinc-800/10 dark:border-white/20" :title="$this->pvProduction && ! $this->pvProduction['exact'] ? __('Interpolated') : null">
                            @if ($this->pvProduction)
                                {{ $this->pvProduction['exact'] ? '' : '~' }}{{ number_format($this->pvProduction['value'], 1) }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="w-5 px-0" />
                        @foreach (\App\Models\MeterDailyReading::VALUE_FIELDS as $i => $field)
                            <flux:table.cell align="end" @class(['font-semibold', 'border-s border-zinc-800/10 dark:border-white/20' => $i % 2 === 0, 'border-s border-zinc-800/5 dark:border-white/10' => $i % 2 === 1])>{{ number_format($this->totals[$field], 3) }}</flux:table.cell>
                        @endforeach
                    </flux:table.row>
                </flux:table.rows>
            @endif
        </flux:table>
        @unless ($this->days)
            <flux:text class="mt-4">{{ __('No readings in this month.') }}</flux:text>
        @endunless
    </flux:card>
</div>
