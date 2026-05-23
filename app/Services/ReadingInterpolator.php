<?php

namespace App\Services;

use App\Models\Reading;
use Carbon\Carbon;
use InvalidArgumentException;

class ReadingInterpolator
{
    /**
     * The five meter fields to interpolate.
     */
    private const FIELDS = [
        'pv_generated',
        'peak_consumed',
        'off_peak_consumed',
        'peak_fed_in',
        'off_peak_fed_in',
    ];

    /**
     * Interpolate meter readings for the given date.
     *
     * Returns an array of the five field values, linearly proportioned
     * between the nearest readings on either side of the date.
     *
     * @return array{date: string, pv_generated: int, peak_consumed: int, off_peak_consumed: int, peak_fed_in: int, off_peak_fed_in: int}
     *
     * @throws InvalidArgumentException if the date is outside the range of recorded readings
     */
    public function forDate(Carbon $date): array
    {
        $before = Reading::whereDate('date', '<=', $date->toDateString())
            ->orderByDesc('date')
            ->first();

        $after = Reading::whereDate('date', '>=', $date->toDateString())
            ->orderBy('date')
            ->first();

        if (! $before || ! $after) {
            throw new InvalidArgumentException(
                "Date {$date->toDateString()} is outside the range of recorded readings."
            );
        }

        // Exact match — no interpolation needed.
        if ($before->date->eq($after->date)) {
            return $this->toArray($date, $before->only(self::FIELDS));
        }

        $totalDays = $before->date->diffInDays($after->date);
        $elapsed = $before->date->diffInDays($date);
        $ratio = $elapsed / $totalDays;

        $values = [];
        foreach (self::FIELDS as $field) {
            $values[$field] = (int) round($before->$field + ($after->$field - $before->$field) * $ratio);
        }

        return $this->toArray($date, $values);
    }

    private function toArray(Carbon $date, array $values): array
    {
        return array_merge(['date' => $date->toDateString()], $values);
    }
}
