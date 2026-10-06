# Solario — Agent Guide

Project conventions (stack, commands, code style) live in `CLAUDE.md`. This file describes the **ongoing rebuild** and how to work within it.

## Rebuild strategy: build alongside, then remove

The application is being rebuilt incrementally using a side-by-side approach:

1. **Add** — the new version of a feature is built *next to* the existing one and gets its **own sidebar entry in the `New` group** (`resources/views/layouts/app/sidebar.blade.php`), so it is usable from day one. The old feature and its entry in the `Old` group stay untouched.
2. **Remove** — once the new feature fully covers the old one, the old feature (its `Old` sidebar entry, routes, views, Livewire components, services, tests) is deleted in a separate, dedicated step.

### Where new code goes

- Pages: `resources/views/pages/new/` (Livewire `pages::new.*`)
- Components: `resources/views/livewire/new/` (`<livewire:new.* />`); plain Blade components in `resources/views/components/new/` (`<x-new.* />`)
- Routes: URL prefix `new/`, route names `new.*`
- Tests: separate test classes (e.g. `NewDashboardTest`), never mixed into legacy test files

### Rules for agents

- **Do not modify legacy code while building a new feature.** If the new feature needs shared logic, copy or extract it rather than changing old behavior. Bug fixes to legacy code only when explicitly asked.
- **Do not delete legacy code unless the task explicitly says to remove it.** Removal is always its own task.
- **Keep both versions working.** `composer test` must pass with old and new features coexisting.
- **No silent cross-wiring.** New code must not depend on legacy code that is scheduled for removal; legacy code must not depend on new code.
- **New code never uses `Season` rates** (`peak_rate`, `off_peak_rate`, `fed_in_ratio`) or the `UsageSummary` cost fields — prices come from the `Price` model. The season rates stay only for the legacy features.
- **New features get their own tests.** Don't rewrite legacy tests to cover new code — when a legacy feature is removed, its tests go with it.
- **Update the status table below** whenever a feature moves between stages (in the same change that moves it).
- When unsure whether a file belongs to the old or new version, check this file and ask rather than guess.

## Migration status

| Feature | Legacy location | New location | Status |
|---------|-----------------|--------------|--------|
| Dashboard | `dashboard.blade.php`, `livewire/⚡*-card`, `⚡*-chart` | `pages::new.dashboard` (`/new/dashboard`) | new available |
| Readings (grid side: consumed / fed-in) | `pages::readings.*` | `MeterDailyReading` model, `MeterCsvImporter`, `pages::new.meter-import.create`, `pages::new.readings.index` | new available |
| PV inverter data (replaces `pv_generated` of Readings) | `pages::readings.create` (PV Generated field) | `PvInverterReading` model, `pages::new.pv-inverter.create` / `.edit` | new available |
| Car charges | `pages::car-charges.*` | — | legacy |
| Seasons | `pages::seasons.*` | — | legacy |
| Pricing (replaces `Season` rates: `peak_rate`, `off_peak_rate`, `fed_in_ratio`) | `Season` rate fields, `UsageSummary` cost fields | `Price` model (`Price::activeOn()`), `pages::new.prices.*`, `<x-new.price-card>` | new available |

> **Known legacy dependencies:** the new dashboard's "Add car charge" button links to the legacy `car-charges.create` route, and the `New` sidebar group has a "Car Charges" link to the legacy `car-charges.index`. Retarget both when car charges are rebuilt.

Status values: `legacy` → `building` (new code exists, not yet usable from the sidebar) → `new available` (entries in both `Old` and `New` groups) → `removed`.
