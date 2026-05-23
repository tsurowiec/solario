# Solario

Solar energy dashboard for tracking PV generation, consumption, and grid feed-in. Built with Laravel, Livewire, and Flux UI.

## Features

- Log daily meter readings (PV generated, peak/off-peak consumed, peak/off-peak fed-in)
- Linear interpolation between readings for any date range
- Monthly usage summaries with self-consumption ratio
- Price calculation based on peak/off-peak tariffs
- Passkey authentication via Laravel Fortify

## Requirements

- PHP 8.3+
- Node.js & npm
- SQLite (default) or any Laravel-supported database

## Setup

```bash
composer run setup
```

This installs dependencies, generates an app key, runs migrations, and builds frontend assets.

## Development

```bash
composer run dev
```

Starts the Laravel server, queue worker, log watcher, and Vite dev server concurrently.

## Testing & Linting

```bash
composer test       # lint check + PHPUnit
composer lint       # auto-fix with Pint
```

## Architecture

| Path | Purpose |
|------|---------|
| `app/Models/Reading.php` | Meter reading model — one row per recorded date |
| `app/Services/ReadingInterpolator.php` | Linear interpolation of meter values between two readings |
| `app/Services/ReadingDiff.php` | Computes delta between two interpolated readings; returns `UsageSummary` |
| `app/Services/PriceCalculator.php` | Calculates monthly bill from a `UsageSummary` using peak/off-peak tariff rates |
| `app/Data/UsageSummary.php` | Readonly value object carrying all usage metrics for a period |
| `resources/views/livewire/⚡usage-card.blade.php` | Livewire component showing monthly usage summary |
| `resources/views/pages/readings/⚡create.blade.php` | Form for entering new meter readings |
