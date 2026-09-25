# CLAUDE.md — PACE project conventions

PACE (Payment Approval and Control Engine) is JF&I Packaging's payment approval platform. Tagline: **"Approvals at PACE"**.
- Brief: `docs/BRIEF.md`
- Plan, architecture and phase list: `PLAN.md`

Read both before starting work in a new session.

## Current status
- **Phase 0 (Plan): done.** Decisions are recorded in PLAN.md §10.
- **Phase 1 (Foundation): in progress.**
- Work goes phase by phase. At the end of each phase: stop, summarise (what was built, how to run and test it, decisions needed), and commit.

## Non-negotiable rules
- **Naming:** the product is **PACE**. Never use "REAP", "X2P" or "Emerald X2P" in code, UI, docs, seeds or commit messages.
- **No entity-specific or approval-specific logic in code.** Nothing like `if ($entity->code === 'KE')`. Anything that differs by entity is a setting, master data or a workflow definition.
- **Entity isolation:** every entity-owned model uses the `BelongsToEntity` trait (global scope plus auto-fill). `entity_id` is never mass-assignable. Every model has a policy that also checks `entity_id`. Foreign IDs return 404. Every new resource gets a test in `tests/Feature/Isolation`.
- **Never trust client IDs.** Re-fetch through scoped queries and authorise inside every Livewire action and controller method.
- **Money:** `DECIMAL(18,2)`, FX rates `DECIMAL(18,6)`. Use `Brick\Math\BigDecimal` via `MoneyCast`. Never use floats. Rounding is HALF_UP.
- **Time:** stored in UTC (`APP_TIMEZONE=UTC`), shown in the current entity's timezone through the display helper.
- **Append-only tables:** `activity_log`, `login_events`, `budget_ledger_entries`, `workflow_actions`. The app never updates or deletes rows in them.
- **Files:** private `documents` disk only, random names, SHA-256 hash stored, served only by `AttachmentController` after a policy check.
- **Mail and notifications are always queued.** Queued jobs carry `entity_id` explicitly and never read the session.
- **Workflow logic lives in the engine** (`app/Domain/Workflow`). Process modules (Capex now) supply facts and forms only.
- **Secrets only in `.env`.** Demo credentials are for local use only, and seeders refuse to run in production.

## Stack (verified 2026-09-25, see PLAN.md §1)
Laravel 13, PHP 8.5, MySQL 9.7 LTS (8.4-compatible), Livewire 4, Filament 5 (admin at `/admin`, plus standalone Filament tables and forms inside the user app), Tailwind 4 (brand colours as `@theme` tokens in `resources/css/app.css`), spatie/laravel-permission 8 (teams: team = entity), spatie/laravel-activitylog 5, maatwebsite/excel 4, barryvdh/laravel-dompdf 3, Pest 5, Larastan 3 (level ≥ 6), Pint.

## Code layout
- Domain code: `app/Domain/{Core,Identity,MasterData,Budget,Workflow,Capex,Documents,Notifications,Audit,Reporting,Integrations}`
- Admin UI: `app/Filament/Admin`
- User UI: `app/Livewire`, `resources/views`
- Tests: `tests/Feature/<Module>` and `tests/Unit`. Tests run on MySQL, not SQLite.

## Conventions
- Business operations are **action classes** (`SubmitCapexRequest::handle()`), called from Livewire, Filament and jobs. Keep them out of components.
- Enums are PHP backed enums in `Domain/*/Enums`.
- Permission names use `{area}.{action}`, for example `capex.view_all`. Workflow steps are assigned by **role**. Screens are gated by **permission**.
- Settings: add every new key to `SettingsRegistry` (type, default, validation). Read them with `Settings::get(key, entity)`.
- Extension seams: `ErpConnector`, `AttachmentScanner`, `ExchangeRateProvider`, `IdentityProvider` and `FactsProvider`. Bind the null or default implementations in service providers.
- Migrations: add foreign keys and indexes on `entity_id` plus common filters. No hard deletes of referenced master data; use `is_active`.
- User-facing strings go through `__()`.

## Commands (available from Phase 1)
```bash
./vendor/bin/sail up -d                 # start app, MySQL, Mailpit
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan queue:work    # queue worker
./vendor/bin/sail artisan schedule:work # scheduler (SLA reminders)
./vendor/bin/sail npm run dev
./vendor/bin/pint --test                # formatting
./vendor/bin/phpstan analyse            # Larastan
./vendor/bin/pest --parallel            # tests
```

## Decisions log
| Date | Decision |
|---|---|
| 2026-09-25 | Stack versions verified via Packagist, npm and Docker Hub (PLAN.md §1). |
| 2026-09-25 | One DB with row-level `entity_id`. Session-based `CurrentEntity`. No Filament URL tenancy. |
| 2026-09-25 | Request numbers are assigned at first submission from a locked `number_sequences` row, so the sequence is gap-free. |
| 2026-09-25 | Custom `Settings` service (group default plus per-entity override) instead of spatie/laravel-settings. |
| 2026-09-25 | Custom `Attachment` model instead of medialibrary, for hash, scanner hook and authorised downloads. |
| 2026-09-25 | Workflow conditions are structured JSON evaluated by typed operators. No expression language and no eval. |

| 2026-09-25 | Phase 0 review: `jfi.lk` domain; FY April–March; annual budget basis; commit on submission; HoD before Coordinator; USD reporting with a full multi-currency ISO 4217 list; admin-defined roles; **process-map workflow designer**; Additional Authorisation amount set in the admin panel (seed workflow is a Draft). |
| 2026-09-25 | Mail: Mailpit locally. Production transport chosen by `MAIL_MAILER`, with Microsoft Graph (`symfony/microsoft-graph-mailer`) recommended. Wired in Phase 6. |

Still open: Kenya ERP name, hosting target, brand colour, M365 mailbox (PLAN.md §10).
