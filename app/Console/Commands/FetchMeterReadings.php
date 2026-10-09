<?php

namespace App\Console\Commands;

use App\Services\MeterCsvImporter;
use App\Services\TauronMeterClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchMeterReadings extends Command
{
    protected $signature = 'meter:fetch
        {--from= : first day (Y-m-d), defaults to the configured lookback before today}
        {--to= : last day (Y-m-d), defaults to today (partial days fill in on later fetches)}';

    protected $description = 'Download the hourly meter data from Tauron eLicznik and import it';

    private const TIMEZONE = 'Europe/Warsaw';

    public function handle(TauronMeterClient $client, MeterCsvImporter $importer): int
    {
        $today = Carbon::today(self::TIMEZONE);
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'), self::TIMEZONE)
            : $today->copy()->subDays((int) config('services.tauron.lookback_days'));
        $to = $this->option('to') ? Carbon::parse($this->option('to'), self::TIMEZONE) : $today->copy();

        try {
            $result = $importer->importString($client->fetchCsv($from, $to));
        } catch (Throwable $e) {
            Log::error('Meter fetch failed: '.$e->getMessage(), ['exception' => $e]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Imported %d day(s): %s', count($result->imported), implode(', ', $result->imported) ?: '-'));

        if ($result->incomplete) {
            $this->warn('Incomplete day(s), will fill in on a later fetch: '.implode(', ', $result->incomplete));
        }

        if ($result->skipped) {
            $this->warn('Skipped day(s) already stored with more hours: '.implode(', ', $result->skipped));
        }

        return self::SUCCESS;
    }
}
