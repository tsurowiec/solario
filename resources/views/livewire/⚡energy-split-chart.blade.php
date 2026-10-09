<?php

use App\Services\EnergyUsage;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {

    /** Any date within the last month shown (Y-m-d). */
    public string $month = '';

    /**
     * Up to 12 months ending at $month; leading months without data are dropped.
     *
     * @return array<string, \App\Data\EnergySummary|null> keyed by the first day of the month (Y-m-d)
     */
    #[Computed]
    public function summaries(): array
    {
        $usage = app(EnergyUsage::class);
        $last = Carbon::parse($this->month)->startOfMonth();
        $summaries = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = $last->copy()->subMonthsNoOverflow($i);
            $summary = $usage->month($month);

            if ($summary === null && $summaries === []) {
                continue;
            }

            $summaries[$month->toDateString()] = $summary;
        }

        return $summaries;
    }

    /**
     * Per month: peak payable, off-peak payable and the rest of the total usage (sun),
     * as % of the total usage (and in kWh for the tooltip).
     */
    #[Computed]
    public function chartData(): array
    {
        $categories = [];
        $percent = ['sun' => [], 'offPeak' => [], 'peak' => []];
        $kwh = ['sun' => [], 'offPeak' => [], 'peak' => []];

        foreach ($this->summaries as $month => $usage) {
            $categories[] = Carbon::parse($month)->format('M');

            $total = $usage?->totalUsage ?? 0;
            $peak = $usage?->peakPayable ?? 0;
            $offPeak = $usage?->offPeakPayable ?? 0;
            $sun = max(0, $total - $peak - $offPeak);

            foreach (compact('sun', 'offPeak', 'peak') as $key => $value) {
                $kwh[$key][] = round($value);
                $percent[$key][] = $total > 0 ? round($value / $total * 100, 1) : 0;
            }
        }

        return compact('categories', 'percent', 'kwh');
    }

}; ?>

<flux:card
    x-data="{
        chart: null,

        async init() {
            await new Promise(resolve => {
                if (window.ApexCharts) { resolve(); return; }
                window.addEventListener('apexcharts-ready', resolve, { once: true });
            });

            const data = JSON.parse(this.$el.dataset.chartData);
            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#a1a1aa' : '#71717a';
            const gridColor = isDark ? '#27272a' : '#e4e4e7';
            const keys = ['sun', 'offPeak', 'peak'];

            this.chart = new window.ApexCharts(this.$refs.chart, {
                chart: {
                    type: 'bar',
                    height: 300,
                    stacked: true,
                    toolbar: { show: false },
                    background: 'transparent',
                    fontFamily: 'inherit',
                },
                series: [
                    { name: 'Sun',              data: data.percent.sun },
                    { name: 'Off-Peak Payable', data: data.percent.offPeak },
                    { name: 'Peak Payable',     data: data.percent.peak },
                ],
                xaxis: {
                    categories: data.categories,
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
                        formatter: (v, { seriesIndex, dataPointIndex }) =>
                            v.toFixed(1) + '% (' + data.kwh[keys[seriesIndex]][dataPointIndex] + ' kWh)',
                    },
                },
            });

            this.chart.render();
        },
    }"
    data-chart-data="{{ json_encode($this->chartData) }}"
>
    <flux:heading size="lg" class="mb-4">{{ __('Energy Split') }}</flux:heading>
    <div x-ref="chart"></div>
</flux:card>
