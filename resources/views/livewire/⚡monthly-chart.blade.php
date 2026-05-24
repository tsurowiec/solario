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
        $last = Carbon::parse(\App\Models\Reading::latest('date')->value('date'));
        $months = (int) $start->diffInMonths($last->copy()->startOfMonth());

        $categories = [];
        $consumed = [];
        $autoConsumed = [];
        $fedIn = [];
        $pvGenerated = [];
        $price = [];

        for ($i = 0; $i <= $months; $i++) {
            $month = $start->copy()->addMonthsNoOverflow($i);
            $usage = $diff->month($month);

            $categories[]   = $month->format('M');
            $consumed[]     = $usage?->consumed ?? 0;
            $autoConsumed[] = $usage?->autoConsumed ?? 0;
            $fedIn[]        = $usage?->fedIn ?? 0;
            $pvGenerated[]  = $usage?->pvGenerated ?? 0;
            $price[]        = $usage ? round($usage->amount, 2) : 0;
        }

        return compact('categories', 'consumed', 'autoConsumed', 'fedIn', 'pvGenerated', 'price');
    }

    #[Computed]
    public function seasonName(): string
    {
        return Season::orderByDesc('starting_date')->value('name') ?? __('Overview');
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
            const kwhMax = Math.max(...data.consumed, ...data.autoConsumed, ...data.fedIn, ...data.pvGenerated, 1) * 1.1;
            const plnMax = Math.max(...data.price, 1) * 1.1;
            const plnMin = Math.min(...data.price, 0) * 1.1;
            // If PLN has negatives, pad kWh below zero by the same proportion
            const kwhMin = plnMin < 0 ? kwhMax * (plnMin / plnMax) : 0;

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
                    { name: 'Consumed',      data: data.consumed,    group: 'usage' },
                    { name: 'Auto-consumed', data: data.autoConsumed, group: 'usage' },
                    { name: 'Fed In',        data: data.fedIn,        group: 'fedIn' },
                    { name: 'Generated',     data: data.pvGenerated,  group: 'solar' },
                    { name: 'Price',         data: data.price,        group: 'price' },
                ],
                xaxis: {
                    categories: data.categories,
                    labels: { style: { colors: textColor } },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                },
                yaxis: [
                    {
                        seriesName: 'Consumed',
                        min: kwhMin, max: kwhMax,
                        labels: { style: { colors: textColor }, formatter: v => Math.round(v) + ' kWh' },
                        title: { text: 'kWh', style: { color: textColor } },
                    },
                    { seriesName: 'Auto-consumed', show: false, min: kwhMin, max: kwhMax },
                    { seriesName: 'Fed In',        show: false, min: kwhMin, max: kwhMax },
                    { seriesName: 'Generated',     show: false, min: kwhMin, max: kwhMax },
                    {
                        seriesName: 'Price',
                        opposite: true,
                        min: plnMin, max: plnMax,
                        labels: { style: { colors: '#60a5fa' }, formatter: v => v.toFixed(0) + ' PLN' },
                        title: { text: 'PLN', style: { color: '#60a5fa' } },
                    },
                ],
                colors: ['#f87171', '#fbbf24', '#4ade80', '#fde047', '#60a5fa'],
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
    <flux:heading size="lg" class="mb-4">{{ $this->seasonName }}</flux:heading>
    <div x-ref="chart"></div>
</flux:card>
