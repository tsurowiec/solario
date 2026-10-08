<?php

namespace App\Services;

use App\Data\MeterImportResult;
use App\Models\MeterDailyReading;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Imports the hourly meter CSV export and stores it as one MeterDailyReading per day.
 *
 * Row format: "Y-m-d H:00;T1|T2;value;type;status;" with decimal commas.
 * Hours run 1:00–24:00 (end of the hour), so "24:00" belongs to the same day.
 * Only consumed and fed-in rows are read; the balanced values are calculated per hour
 * (hourly net-metering: balanced consumed = max(0, consumed − fed-in), balanced fed-in the reverse),
 * so the CSV's own balanced rows, published later by the operator, are ignored.
 * Only complete days (every hour present for both types) are saved; existing days are overwritten.
 */
class MeterCsvImporter
{
    /**
     * Local timezone of the meter, used to know how many hours a day has (DST days have 23 or 25).
     */
    private const TIMEZONE = 'Europe/Warsaw';

    private const STATUS = 'licznik';

    private const TYPES = [
        'pobór [kWh]' => 'consumed',
        'oddanie [kWh]' => 'fed_in',
    ];

    private const ZONES = ['T1' => 't1', 'T2' => 't2'];

    public function import(string $path): MeterImportResult
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new InvalidArgumentException("Cannot read file {$path}.");
        }

        return $this->importString($content);
    }

    public function importString(string $content): MeterImportResult
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1250');
        }

        // Values are kept in Wh (integers) to avoid float rounding errors.
        // Hourly values are grouped by date and "hour|zone"; a list per type keeps repeated hours (DST) apart.
        $hourly = [];
        $hourCounts = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $cells = array_map('trim', explode(';', $line));

            if (count($cells) < 5 || $cells[4] !== self::STATUS) {
                continue;
            }

            [$dateTime, $zone, $value, $type] = $cells;

            if (! isset(self::ZONES[$zone], self::TYPES[$type])
                || ! preg_match('/^(\d{4}-\d{2}-\d{2}) \d{1,2}:\d{2}$/', $dateTime, $m)
                || ! preg_match('/^\d+(,\d{1,3})?$/', $value)) {
                continue;
            }

            $date = $m[1];

            $hourly[$date][$dateTime.'|'.$zone][self::TYPES[$type]][] = $this->toWh($value);
            $hourCounts[$date][$type] = ($hourCounts[$date][$type] ?? 0) + 1;
        }

        $totals = array_map($this->sumDay(...), $hourly);

        ksort($totals);

        $imported = [];
        $skipped = [];

        foreach ($totals as $date => $fields) {
            if (! $this->isComplete($date, $hourCounts[$date])) {
                $skipped[] = $date;

                continue;
            }

            $values = [];
            foreach (MeterDailyReading::VALUE_FIELDS as $field) {
                $values[$field] = ($fields[$field] ?? 0) / 1000;
            }

            // Look up via whereDate: the date cast stores "Y-m-d 00:00:00", so a plain where wouldn't match.
            $reading = MeterDailyReading::whereDate('date', $date)->first() ?? new MeterDailyReading(['date' => $date]);
            $reading->fill($values)->save();
            $imported[] = $date;
        }

        return new MeterImportResult($imported, $skipped);
    }

    /**
     * Sums a day's hourly values per zone, netting consumed against fed-in within each hour.
     *
     * @param  array<string, array<string, list<int>>>  $hours  values in Wh per "hour|zone" and type
     * @return array<string, int> totals in Wh per field
     */
    private function sumDay(array $hours): array
    {
        $totals = [];

        foreach ($hours as $key => $values) {
            $zone = self::ZONES[explode('|', $key)[1]];
            $consumedList = $values['consumed'] ?? [];
            $fedInList = $values['fed_in'] ?? [];

            foreach (array_keys($consumedList + $fedInList) as $i) {
                $consumed = $consumedList[$i] ?? 0;
                $fedIn = $fedInList[$i] ?? 0;

                $this->add($totals, "{$zone}_consumed", $consumed);
                $this->add($totals, "{$zone}_fed_in", $fedIn);
                $this->add($totals, "{$zone}_balanced_consumed", max(0, $consumed - $fedIn));
                $this->add($totals, "{$zone}_balanced_fed_in", max(0, $fedIn - $consumed));
            }
        }

        return $totals;
    }

    /**
     * @param  array<string, int>  $totals
     */
    private function add(array &$totals, string $field, int $wh): void
    {
        $totals[$field] = ($totals[$field] ?? 0) + $wh;
    }

    /**
     * @param  array<string, int>  $counts  number of hourly rows per type
     */
    private function isComplete(string $date, array $counts): bool
    {
        $start = Carbon::parse($date, self::TIMEZONE)->startOfDay();
        $hours = (int) $start->diffInHours($start->copy()->addDay()->startOfDay());

        foreach (array_keys(self::TYPES) as $type) {
            if (($counts[$type] ?? 0) !== $hours) {
                return false;
            }
        }

        return true;
    }

    private function toWh(string $value): int
    {
        [$whole, $fraction] = array_pad(explode(',', $value), 2, '');

        return (int) $whole * 1000 + (int) str_pad($fraction, 3, '0');
    }
}
