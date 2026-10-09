# Solario — Claude Code Guide

## Commands

```bash
composer run dev        # start all dev processes (server, queue, logs, vite)
composer test           # lint check + PHPUnit
composer lint           # auto-fix code style with Pint
php artisan migrate     # run pending migrations
```

## Stack

- **Laravel 13** + **Livewire 4** + **Flux UI 2** (component library)
- **SQLite** by default (`database/database.sqlite`)
- **Laravel Fortify** for auth, including passkey support
- **Vite** for frontend assets; **Tailwind CSS** via Flux

## Key Conventions

### Livewire components
Single-file components live in `resources/views/livewire/` with the `⚡` prefix (e.g. `⚡month-card.blade.php`). The PHP class is defined inline at the top of the blade file using `new class extends Component`.

### Value Objects
Domain data is returned as readonly VOs from `app/Data/`. Use camelCase public properties. Derived fields are computed in the constructor.

### Services
- `PvInverterInterpolator` — `->forDate()` returns the PV inverter value (`float`) for a day, linearly proportioned between the surrounding `PvInverterReading`s; throws outside their range. An optional day fraction estimates the value part way into a day (used for a last day the meter covers only partly: `MeterDailyReading::dayFraction()` is the share of the day's PV made in its covered hours, on a sine curve between sunrise and sunset at `PV_LATITUDE` / `PV_LONGITUDE`)
- `EnergyUsage` — `->month()` returns `EnergySummary` VO (PV from inverter, raw consumed / fed-in from `MeterDailyReading`) for the month's full-data days, stopping at the first gap; null when none
- `MeterCsvImporter` — imports the hourly meter CSV into `MeterDailyReading` (one row per day, partial days included with their `hours`; a day is never overwritten by one with fewer hours); returns `MeterImportResult` VO
- `TauronMeterClient` — `->fetchCsv($from, $to)` logs in to Tauron eLicznik (ported from mlesniew/elicznik) and downloads the hourly meter CSV; the `meter:fetch` command imports it via `MeterCsvImporter` (scheduled every 2 hours on the hour, Europe/Warsaw, last `TAURON_LOOKBACK_DAYS` days up to today; needs `TAURON_USERNAME` / `TAURON_PASSWORD`)

### Pricing
Prices are stored in the `Price` model (per-kWh and monthly prices in PLN). `Price::activeOn($date)` returns the prices that apply to a date. Costs in `EnergySummary` are calculated from the active `Price`.

### Date handling
Always use local date components (never `toISOString()` in JS) to avoid UTC offset issues. In JS: `getFullYear()` / `getMonth()` / `getDate()`.

### Code style
Pint with default Laravel ruleset. Run `composer lint` before committing.
