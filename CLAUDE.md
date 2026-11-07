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
- `PvInverterInterpolator` — `->forDate()` returns the PV inverter value (`float`) for a day, linearly proportioned between the surrounding `PvInverterReading`s; throws outside their range. An optional day fraction estimates the value part way into a day (used for a last day the meter covers only partly: `MeterDailyReading::pvFraction()` is the share of the day's PV made in its covered hours, on a sine curve between sunrise and sunset at `PV_LATITUDE` / `PV_LONGITUDE`)
- `EnergyUsage` — `->month()` returns `EnergySummary` VO (PV from inverter, raw consumed / fed-in from `MeterDailyReading`, meter zones mapped to tariff zones by `Price::meterZones()`) for the month's full-data days, stopping at the first gap; null when none. Credit left after a month's settlement (`EnergySummary::$flows`, one `ZoneFlow` per zone) carries into the next month, worked forward from the first meter month. A partial last day counts as part of a day: `coveredDays` (by hours, for grid / cost averages, fee proration and the month estimate) and `pvDays` (by PV share, for PV and self-consumed averages)
- `MeterCsvImporter` — imports the hourly meter CSV into `MeterDailyReading` (one row per day, T1–T3, partial days included with their `hours`; a day is never overwritten by one with fewer hours); returns `MeterImportResult` VO
- `TauronMeterClient` — `->fetchCsv($from, $to)` logs in to Tauron eLicznik (ported from mlesniew/elicznik) and downloads the hourly meter CSV; the `meter:fetch` command imports it via `MeterCsvImporter` (scheduled at 08:00 and 20:00 Europe/Warsaw, last `TAURON_LOOKBACK_DAYS` days up to today; after a refused login — wrong credentials or a block, which Tauron extends by 8 h on every attempt — it pauses for 9 h, `--force` overrides; scheduled runs pass `--jitter=600` to wait a random 0–600 s before logging in; needs `TAURON_USERNAME` / `TAURON_PASSWORD`)

### Pricing
Prices are stored in the `Price` model (per-kWh and monthly prices in PLN). `Price::activeOn($date)` returns the prices that apply to a date. Costs in `EnergySummary` are calculated from the active `Price`.

Tariff zones are peak, early-peak and off-peak (`Price::ZONES`, most expensive first). Any early-peak price above 0 (`Price::hasEarlyPeak()`) switches to three zones: T1 morning = early-peak, T2 evening = peak, T3 = off-peak. Otherwise two zones: T1 = peak, T2 + T3 = off-peak. Settlement per month: carried credit first, then each zone's 0.8 × balanced fed-in against its own balanced consumed, then the pooled surplus, each time most expensive zone first; the rest carries over. Icons: sun / moon for two zones, sunrise / sun / moon for three.

### Date handling
Always use local date components (never `toISOString()` in JS) to avoid UTC offset issues. In JS: `getFullYear()` / `getMonth()` / `getDate()`.

### Code style
Pint with default Laravel ruleset. Run `composer lint` before committing.
