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
- `ReadingDiff` — returns `UsageSummary` VO; use `->month()`, `->day()`, or `->between()`
- `PriceCalculator` — accepts `UsageSummary`, returns `float` (PLN)

### Tariff constants (PriceCalculator)
| Constant | Value |
|----------|-------|
| `PEAK_RATE` | 1.40 PLN/kWh |
| `OFF_PEAK_RATE` | 0.70 PLN/kWh |
| `FED_IN_RATIO` | 0.80 |

### Date handling
Always use local date components (never `toISOString()` in JS) to avoid UTC offset issues. In JS: `getFullYear()` / `getMonth()` / `getDate()`.

### Code style
Pint with default Laravel ruleset. Run `composer lint` before committing.
