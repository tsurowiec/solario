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
Single-file components live in `resources/views/livewire/` with the `⚡` prefix (e.g. `⚡usage-card.blade.php`). The PHP class is defined inline at the top of the blade file using `new class extends Component`.

### Value Objects
Domain data is returned as readonly VOs from `app/Data/`. Use camelCase public properties. Derived fields are computed in the constructor.

### Services
- `ReadingInterpolator` — always returns an array (internal, not exposed as VO)
- `ReadingDiff` — returns `UsageSummary` VO; use `->day()`, `->month()`, `->year()`, `->season()`, or `->between()`. Passes the rates of the relevant `Season` into the VO
- `PvInverterInterpolator` — `->forDate()` returns the PV inverter value (`float`) for a day, linearly proportioned between the surrounding `PvInverterReading`s; throws outside their range
- `EnergyUsage` — `->month()` returns `EnergySummary` VO (PV from inverter, raw consumed / fed-in from `MeterDailyReading`) for the month's full-data days, stopping at the first gap; null when none
- `MeterCsvImporter` — imports the hourly meter CSV into `MeterDailyReading` (one row per complete day); returns `MeterImportResult` VO
- `CarChargeUsage` — returns charged kWh (`int`) for a car; use `->month()`, `->year()`, `->season()`, or `->between()`

### Pricing (per season)
Tariff rates are stored per `Season` (`peak_rate`, `off_peak_rate`, `fed_in_ratio`). `Season::activeOn($date)` returns the season that applies to a date (the latest one that has started by then). Costs (`amount`, `pricePerUnit`, payable values) are calculated in the `UsageSummary` constructor. When no season applies, the VO falls back to its defaults: 1.40 / 0.70 PLN/kWh, fed-in ratio 0.80.

**Legacy only.** The rebuild uses the `Price` model instead (per-kWh and monthly prices in PLN, `Price::activeOn($date)`); new code must not use season rates — see `AGENTS.md`.

### Date handling
Always use local date components (never `toISOString()` in JS) to avoid UTC offset issues. In JS: `getFullYear()` / `getMonth()` / `getDate()`.

### Code style
Pint with default Laravel ruleset. Run `composer lint` before committing.

## Rebuild in progress

The app is being rebuilt side by side (new features next to old ones, then the old ones are removed). Read and follow:

@AGENTS.md
