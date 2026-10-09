# Solario

Solar energy dashboard for tracking PV generation, consumption, and grid feed-in. Built with Laravel, Livewire, and Flux UI.

## Features

- Hourly grid meter data (consumed / fed-in, peak / off-peak) imported from a CSV or fetched daily from Tauron eLicznik (`meter:fetch`)
- PV inverter readings, linearly interpolated per day
- Car charge log per car
- Prices (per-kWh and monthly fees) with validity dates
- Dashboard with monthly charts and a month summary: self-consumption, net-metering balance, costs, and the split between cars and household
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
| `app/Models/MeterDailyReading.php` | Grid meter totals, one row per day (`hours` = hours covered) |
| `app/Models/PvInverterReading.php` | PV inverter counter readings |
| `app/Models/CarCharge.php` | Charged kWh per car and date |
| `app/Models/Price.php` | Prices; `Price::activeOn($date)` |
| `app/Services/MeterCsvImporter.php` | Imports the hourly meter CSV into `MeterDailyReading` |
| `app/Services/TauronMeterClient.php` | Downloads the meter CSV from Tauron eLicznik |
| `app/Services/PvInverterInterpolator.php` | PV value for a day, interpolated between inverter readings |
| `app/Services/EnergyUsage.php` | Monthly `EnergySummary` (usage, balance, costs) |
| `app/Data/EnergySummary.php` | Readonly value object with the month's metrics |
| `resources/views/pages/` | Livewire pages: dashboard, readings, meter import, PV inverter, car charges, prices |
| `resources/views/livewire/` | Dashboard components: month card, monthly chart, energy split chart |
