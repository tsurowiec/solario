# Solario — Agent Guide

Project conventions (stack, commands, code style) live in `CLAUDE.md`. This file describes the **ongoing rebuild** and how to work within it.

## Rebuild strategy: build alongside, then remove

The application is being rebuilt incrementally using a side-by-side approach:

1. **Add** — the new version of a feature is built *next to* the existing one and gets its **own sidebar entry in the `New` group** (`resources/views/layouts/app/sidebar.blade.php`), so it is usable from day one. The old feature and its entry in the `Old` group stay untouched.
2. **Remove** — once the new feature fully covers the old one, the old feature (its `Old` sidebar entry, routes, views, Livewire components, services, tests) is deleted in a separate, dedicated step.

### Rules for agents

- **Do not modify legacy code while building a new feature.** If the new feature needs shared logic, copy or extract it rather than changing old behavior. Bug fixes to legacy code only when explicitly asked.
- **Do not delete legacy code unless the task explicitly says to remove it.** Removal is always its own task.
- **Keep both versions working.** `composer test` must pass with old and new features coexisting.
- **No silent cross-wiring.** New code must not depend on legacy code that is scheduled for removal; legacy code must not depend on new code.
- **New features get their own tests.** Don't rewrite legacy tests to cover new code — when a legacy feature is removed, its tests go with it.
- **Update the status table below** whenever a feature moves between stages (in the same change that moves it).
- When unsure whether a file belongs to the old or new version, check this file and ask rather than guess.

## Migration status

| Feature | Legacy location | New location | Status |
|---------|-----------------|--------------|--------|
| Dashboard | `dashboard.blade.php`, `livewire/⚡*-card`, `⚡*-chart` | — | legacy |
| Readings | `pages::readings.*` | — | legacy |
| Car charges | `pages::car-charges.*` | — | legacy |
| Seasons | `pages::seasons.*` | — | legacy |

Status values: `legacy` → `new available` (entries in both `Old` and `New` groups) → `removed`.
