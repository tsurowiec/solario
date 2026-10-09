<?php

namespace App\Data;

readonly class MeterImportResult
{
    /**
     * @param  list<string>  $imported  dates (Y-m-d) saved, new or overwritten
     * @param  list<string>  $incomplete  dates (Y-m-d) among $imported saved with fewer hours than a full day
     * @param  list<string>  $skipped  dates (Y-m-d) not saved because the stored day already has more hours
     */
    public function __construct(
        public array $imported,
        public array $incomplete,
        public array $skipped,
    ) {}
}
