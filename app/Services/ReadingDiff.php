<?php

namespace App\Services;

use App\Data\UsageSummary;
use App\Models\Reading;
use Carbon\Carbon;

class ReadingDiff
{
    public function __construct(private readonly ReadingInterpolator $interpolator) {}

    public function month(Carbon $date): ?UsageSummary
    {
        $first = Carbon::parse(Reading::oldest('date')->value('date'))->startOfDay();
        $last = Carbon::parse(Reading::latest('date')->value('date'))->startOfDay();

        $from = $date->copy()->startOfMonth()->subDay()->max($first);
        $to = $date->copy()->endOfMonth()->startOfDay()->min($last);

        if ($from->gt($to)) {
            return null;
        }

        return $this->between($from, $to);
    }

    public function day(Carbon $date): UsageSummary
    {
        return $this->between($date->copy()->subDay(), $date);
    }

    public function between(Carbon $from, Carbon $to): UsageSummary
    {
        $a = $this->interpolator->forDate($from);
        $b = $this->interpolator->forDate($to);

        return new UsageSummary(
            from: $from->toDateString(),
            to: $to->toDateString(),
            pvGenerated: $b['pv_generated'] - $a['pv_generated'],
            peakConsumed: $b['peak_consumed'] - $a['peak_consumed'],
            offPeakConsumed: $b['off_peak_consumed'] - $a['off_peak_consumed'],
            peakFedIn: $b['peak_fed_in'] - $a['peak_fed_in'],
            offPeakFedIn: $b['off_peak_fed_in'] - $a['off_peak_fed_in'],
        );
    }
}
