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

    #[Computed]
    public function chartData(): array
    {
        $categories = [];
        $offPeakPaid = [];
        $earlyPeakPaid = [];
        $peakPaid = [];
        $balanced = [];
        $credited = [];
        $autoConsumed = [];
        $fedIn = [];
        $pvGenerated = [];
        $price = [];

        foreach ($this->summaries as $month => $usage) {
            $categories[] = Carbon::parse($month)->format('M');
            // Raw grid consumption split into what hourly balancing cancelled 1:1,
            // what fed-in credit (0.8×) settled and what is paid for.
            $offPeakPaid[] = round($usage?->offPeakPayable ?? 0, 1);
            $earlyPeakPaid[] = round($usage?->earlyPeakPayable ?? 0, 1);
            $peakPaid[] = round($usage?->peakPayable ?? 0, 1);
            $balanced[] = round($usage?->hourlyBalanced ?? 0, 1);
            $credited[] = round($usage?->credited ?? 0, 1);
            $autoConsumed[] = round($usage?->autoConsumed ?? 0, 1);
            $fedIn[] = round($usage?->fedIn ?? 0, 1);
            $pvGenerated[] = round($usage?->pvGenerated ?? 0, 1);
            $price[] = round($usage?->amount ?? 0, 2);
        }

        return compact('categories', 'offPeakPaid', 'earlyPeakPaid', 'peakPaid', 'balanced', 'credited', 'autoConsumed', 'fedIn', 'pvGenerated', 'price');
    }

    #[Computed]
    public function label(): string
    {
        $months = array_keys($this->summaries);

        if ($months === []) {
            return '';
        }

        $first = Carbon::parse($months[0])->format('M Y');
        $last = Carbon::parse(end($months))->format('M Y');

        return $first === $last ? $first : $first.' – '.$last;
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

            // Compute axis bounds so zero aligns on both axes.
            // The zero position fraction = |min| / (|min| + max) must match.
            // Auto-consumed, balanced, credited and paid per zone are stacked, so their sum is what must fit.
            const usage = data.autoConsumed.map((v, i) =>
                v + data.balanced[i] + data.credited[i] + data.offPeakPaid[i] + data.earlyPeakPaid[i] + data.peakPaid[i]);
            const kwhMax = Math.max(...usage, ...data.fedIn, ...data.pvGenerated, 1) * 1.1;
            const plnMax = Math.max(...data.price, 1) * 1.1;
            const plnMin = Math.min(...data.price, 0) * 1.1;
            // If PLN has negatives, pad kWh below zero by the same proportion
            const kwhMin = plnMin < 0 ? kwhMax * (plnMin / plnMax) : 0;

            // Paid is split per tariff zone, light to dark red as the price rises;
            // early-peak is left out when no month has it (two-zone tariff).
            const series = [
                { name: 'Auto-consumed',   data: data.autoConsumed,  group: 'usage', color: '#a3d977' },
                { name: 'Balanced (1:1)',  data: data.balanced,      group: 'usage', color: '#5fb041' },
                { name: 'Credit (0.8×)',   data: data.credited,      group: 'usage', color: '#2f7a2a' },
                { name: 'Paid off-peak',   data: data.offPeakPaid,   group: 'usage', color: '#f87171' },
                { name: 'Paid early-peak', data: data.earlyPeakPaid, group: 'usage', color: '#dc2626' },
                { name: 'Paid peak',       data: data.peakPaid,      group: 'usage', color: '#991b1b' },
                { name: 'Fed In',          data: data.fedIn,         group: 'fedIn', color: '#fbbf24' },
                { name: 'Generated',       data: data.pvGenerated,   group: 'solar', color: '#fde047' },
                { name: 'Price',           data: data.price,         group: 'price', color: '#60a5fa' },
            ].filter(s => s.name !== 'Paid early-peak' || s.data.some(v => v > 0));

            this.chart = new window.ApexCharts(this.$refs.chart, {
                chart: {
                    type: 'bar',
                    height: 300,
                    stacked: true,
                    toolbar: { show: false },
                    background: 'transparent',
                    fontFamily: 'inherit',
                },
                series,
                xaxis: {
                    categories: data.categories,
                    labels: { style: { colors: textColor } },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                },
                yaxis: [
                    ...series.slice(0, -1).map((s, i) => i === 0
                        ? {
                            seriesName: s.name,
                            min: kwhMin, max: kwhMax,
                            labels: { style: { colors: textColor }, formatter: v => Math.round(v) + ' kWh' },
                            title: { text: 'kWh', style: { color: textColor } },
                        }
                        : { seriesName: s.name, show: false, min: kwhMin, max: kwhMax }),
                    {
                        seriesName: 'Price',
                        opposite: true,
                        min: plnMin, max: plnMax,
                        labels: { style: { colors: '#60a5fa' }, formatter: v => v.toFixed(0) + ' PLN' },
                        title: { text: 'PLN', style: { color: '#60a5fa' } },
                    },
                ],
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
                        formatter: (v, { seriesIndex, w }) =>
                            w.config.series[seriesIndex].name === 'Price'
                                ? v.toFixed(2) + ' PLN'
                                : Math.round(v) + ' kWh',
                    },
                },
            });

            this.chart.render();
        },
    }"
    data-chart-data="{{ json_encode($this->chartData) }}"
>
    <flux:heading size="lg" class="mb-4">{{ $this->label }}</flux:heading>
    <div x-ref="chart"></div>
</flux:card>
