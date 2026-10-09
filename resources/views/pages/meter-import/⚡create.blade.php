<?php

use App\Services\MeterCsvImporter;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Import Meter Data')]
#[Layout('layouts.app', ['title' => 'Import Meter Data'])]
class extends Component {
    use WithFileUploads;

    public $file;

    /** @var list<string> */
    public array $imported = [];

    /** @var list<string> */
    public array $incomplete = [];

    /** @var list<string> */
    public array $skipped = [];

    public bool $done = false;

    public function import(MeterCsvImporter $importer): void
    {
        $this->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $result = $importer->import($this->file->getRealPath());

        $this->imported = $result->imported;
        $this->incomplete = $result->incomplete;
        $this->skipped = $result->skipped;
        $this->done = true;
        $this->reset('file');

        Flux::toast(
            variant: $result->imported ? 'success' : 'warning',
            text: trans_choice(':count day imported.|:count days imported.', count($result->imported)),
        );
    }

}; ?>

<div class="mx-auto max-w-lg w-full space-y-6">
    <div>
        <flux:heading size="xl" class="mb-1">{{ __('Import Meter Data') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Upload the hourly CSV export from the meter. Data is combined per day; incomplete days are saved and fill in on a later import. Existing days are overwritten unless they already cover more hours.') }}</flux:subheading>

        <form wire:submit="import" class="space-y-5">
            <flux:card>
                <flux:input wire:model="file" :label="__('CSV file')" type="file" accept=".csv,text/csv" required />
            </flux:card>

            <flux:button variant="primary" type="submit" icon="arrow-up-tray">
                {{ __('Import') }}
            </flux:button>
        </form>
    </div>

    @if ($done)
        <flux:card class="space-y-3">
            <flux:heading>{{ __('Import result') }}</flux:heading>

            <div>
                <div class="flex items-center gap-2">
                    <flux:icon name="check-circle" class="text-green-400 shrink-0" variant="mini" />
                    <flux:text>{{ trans_choice(':count day imported|:count days imported', count($imported)) }}</flux:text>
                </div>
                @if ($imported)
                    <ul class="mt-1 ms-7 list-disc ps-4 text-sm text-zinc-500 dark:text-zinc-400">
                        @foreach ($imported as $date)
                            <li>{{ $date }}@if (in_array($date, $incomplete)) <span class="text-yellow-500">({{ __('incomplete') }})</span>@endif</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if ($skipped)
                <div>
                    <div class="flex items-center gap-2">
                        <flux:icon name="exclamation-triangle" class="text-yellow-400 shrink-0" variant="mini" />
                        <flux:text>{{ trans_choice(':count day skipped (already stored with more hours)|:count days skipped (already stored with more hours)', count($skipped)) }}</flux:text>
                    </div>
                    <ul class="mt-1 ms-7 list-disc ps-4 text-sm text-zinc-500 dark:text-zinc-400">
                        @foreach ($skipped as $date)
                            <li>{{ $date }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </flux:card>
    @endif
</div>
