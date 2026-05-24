<?php

use App\Models\Season;
use App\Services\ReadingDiff;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {

    #[Computed]
    public function chartData(): array
    {
        $diff = app(ReadingDiff::class);
        $season = Season::orderByDesc('starting_date')->first();
        $start = $season
            ? Carbon::parse($season->starting_date)->startOfMonth()
            : Carbon::parse(\App\Models\Reading::oldest('date')->value('date'))->startOfMonth();
        $lastReading = Carbon::parse(\App\Models\Reading::latest('date')->value('date'));
        $months = (int) $start->diffInMonths($lastReading->copy()->startOfMonth());

        $categories = [];
        $monthly    = ['peak' => [], 'offPeak' => [], 'sun' => [], 'totalUsage' => []];
        $cumulative = ['peak' => [], 'offPeak' => [], 'sun' => [], 'totalUsage' => []];

        $firstReading = Carbon::parse(\App\Models\Reading::oldest('date')->value('date'))->startOfDay();
        $seasonFrom = $season
            ? Carbon::parse($season->starting_date)->subDay()->max($firstReading)
            : $firstReading;
        $lastReadingDay = $lastReading->copy()->startOfDay();

        for ($i = 0; $i <= $months; $i++) {
            $month = $start->copy()->addMonthsNoOverflow($i);
            $usage = $diff->month($month);

            $categories[] = $month->format('M');

            $tu = $usage?->totalUsage ?? 0;
            $monthly['peak'][]       = round($usage ? $usage->peakRatio * 100 : 0.0, 1);
            $monthly['offPeak'][]    = round($usage ? $usage->offPeakRatio * 100 : 0.0, 1);
            $monthly['sun'][]        = round($usage ? $usage->sunRatio * 100 : 0.0, 1);
            $monthly['totalUsage'][] = $tu;

            $cumTo  = $month->copy()->endOfMonth()->startOfDay()->min($lastReadingDay);
            $cumUsage = $diff->between($seasonFrom, $cumTo);
            $cumulative['peak'][]       = round($cumUsage->peakRatio * 100, 1);
            $cumulative['offPeak'][]    = round($cumUsage->offPeakRatio * 100, 1);
            $cumulative['sun'][]        = round($cumUsage->sunRatio * 100, 1);
            $cumulative['totalUsage'][] = $cumUsage->totalUsage;
        }

        return compact('categories', 'monthly', 'cumulative');
    }

}; ?>

<flux:card
    x-data="{
        chart: null,
        mode: 'monthly',
        data: null,

        async init() {
            await new Promise(resolve => {
                if (window.ApexCharts) { resolve(); return; }
                window.addEventListener('apexcharts-ready', resolve, { once: true });
            });

            this.data = JSON.parse(this.$el.dataset.chartData);
            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#a1a1aa' : '#71717a';
            const gridColor = isDark ? '#27272a' : '#e4e4e7';

            const self = this;

            this.chart = new window.ApexCharts(this.$refs.chart, {
                chart: {
                    type: 'bar',
                    height: 300,
                    stacked: true,
                    toolbar: { show: false },
                    background: 'transparent',
                    fontFamily: 'inherit',
                },
                series: this.buildSeries('monthly'),
                xaxis: {
                    categories: this.data.categories,
                    labels: { style: { colors: textColor } },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                },
                yaxis: {
                    min: 0,
                    max: 100,
                    labels: {
                        style: { colors: textColor },
                        formatter: v => Math.round(v) + '%',
                    },
                },
                colors: ['#fde047', '#4ade80', '#f87171'],
                plotOptions: {
                    bar: { columnWidth: '55%', borderRadius: 3, borderRadiusWhenStacked: 'last' },
                },
                dataLabels: { enabled: false },
                legend: {
                    labels: { colors: textColor },
                    position: 'top',
                    horizontalAlign: 'right',
                },
                grid: {
                    borderColor: gridColor,
                    strokeDashArray: 4,
                },
                tooltip: {
                    theme: isDark ? 'dark' : 'light',
                    y: {
                        formatter(v, { dataPointIndex }) {
                            const usage = self.data[self.mode].totalUsage[dataPointIndex];
                            const kwh = Math.round(v / 100 * usage);
                            return v.toFixed(1) + '% (' + kwh + ' kWh)';
                        },
                    },
                },
            });

            this.chart.render();
        },

        buildSeries(mode) {
            const d = this.data[mode];
            return [
                { name: 'Sun Generated', data: d.sun },
                { name: 'Off-Peak',      data: d.offPeak },
                { name: 'Peak',          data: d.peak },
            ];
        },

        toggle() {
            this.mode = this.mode === 'monthly' ? 'cumulative' : 'monthly';
            this.chart.updateSeries(this.buildSeries(this.mode));
        },
    }"
    data-chart-data="{{ json_encode($this->chartData) }}"
>
    <div class="flex items-center justify-between gap-4 mb-4">
        <flux:heading size="lg" class="truncate" x-text="'Energy Split — ' + (mode === 'monthly' ? 'Monthly' : 'Cumulative')"></flux:heading>
        <flux:button class="" x-on:click="toggle()" >Switch chart type</flux:button>
    </div>
    <div x-ref="chart"></div>
</flux:card>
