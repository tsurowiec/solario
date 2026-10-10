<?php

namespace App\Console\Commands;

use App\Exceptions\TauronLoginException;
use App\Services\MeterCsvImporter;
use App\Services\TauronMeterClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

class FetchMeterReadings extends Command
{
    protected $signature = 'meter:fetch
        {--from= : first day (Y-m-d), defaults to the configured lookback before today}
        {--to= : last day (Y-m-d), defaults to today (partial days fill in on later fetches)}
        {--force : log in even while paused after a refused login}
        {--jitter=0 : wait a random 0 to this many seconds before contacting eLicznik}';

    protected $description = 'Download the hourly meter data from Tauron eLicznik and import it';

    private const TIMEZONE = 'Europe/Warsaw';

    /** Cache key holding the time until which logins are paused. */
    public const PAUSE_KEY = 'meter-fetch:paused-until';

    /** Tauron blocks for 8 hours and every attempt meanwhile extends the block, so wait a bit longer. */
    private const PAUSE_HOURS = 9;

    public function handle(TauronMeterClient $client, MeterCsvImporter $importer): int
    {
        $pausedUntil = Cache::get(self::PAUSE_KEY);

        if ($pausedUntil && ! $this->option('force')) {
            $this->warn("Skipped: Tauron refused the last login, paused until {$pausedUntil} (use --force to try anyway).");

            return self::SUCCESS;
        }

        // Spread scheduled logins so they don't hit eLicznik at the exact same time every day.
        if (($jitter = (int) $this->option('jitter')) > 0) {
            $wait = random_int(0, $jitter);
            $this->line("Waiting {$wait} s before contacting eLicznik.");
            Sleep::for($wait)->seconds();
        }

        $today = Carbon::today(self::TIMEZONE);
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'), self::TIMEZONE)
            : $today->copy()->subDays((int) config('services.tauron.lookback_days'));
        $to = $this->option('to') ? Carbon::parse($this->option('to'), self::TIMEZONE) : $today->copy();

        try {
            $result = $importer->importString($client->fetchCsv($from, $to));
        } catch (TauronLoginException $e) {
            $until = now(self::TIMEZONE)->addHours(self::PAUSE_HOURS);
            Cache::put(self::PAUSE_KEY, $until->format('Y-m-d H:i T'), $until);
            Log::error('Meter fetch login refused, pausing until '.$until->format('Y-m-d H:i T').': '.$e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
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
