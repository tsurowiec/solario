<?php

namespace App\Data;

readonly class MeterImportResult
{
    /**
     * @param  list<string>  $imported  dates (Y-m-d) saved, new or overwritten
     * @param  list<string>  $skipped  dates (Y-m-d) skipped because their data was incomplete
     */
    public function __construct(
        public array $imported,
        public array $skipped,
    ) {}
}
