# Solario — Agent Guide

Project conventions (stack, commands, services, code style) live in `CLAUDE.md`.

The side-by-side rebuild is complete: the legacy features (old dashboard, `Reading`-based readings, old car charge pages, `Season` tariffs) were removed, the new versions now use the plain names (no `new/` prefix), and the `readings` / `seasons` tables are dropped by the `drop_legacy_tables` migration.
