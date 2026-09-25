# CLAUDE.md — PACE project conventions

PACE (Payment Approval and Control Engine) is JF&I Packaging's payment approval platform. Tagline: **"Approvals at PACE"**.
- Brief: `docs/BRIEF.md`
- Plan, architecture and phase list: `PLAN.md`

Read both before starting work in a new session.

## Current status
- **Phase 0 (Plan): done.** Decisions are recorded in PLAN.md §10.
- **Phase 1 (Foundation): done.**
- **Phase 2 (Master data): done.** Waiting for review before Phase 3 (Budgets).
- Work goes phase by phase. At the end of each phase: stop, summarise (what was built, how to run and test it, decisions needed), and commit.

## Non-negotiable rules
- **Naming:** the product is **PACE**. Never use "REAP", "X2P" or "Emerald X2P" in code, UI, docs, seeds or commit messages.
- **No entity-specific or approval-specific logic in code.** Nothing like `if ($entity->code === 'KE')`. Anything that differs by entity is a setting, master data or a workflow definition.
- **Entity isolation:** every entity-owned model uses the `BelongsToEntity` trait (global scope plus auto-fill). `entity_id` is never mass-assignable. Every model has a policy that also checks `entity_id`. Foreign IDs return 404. Every new resource gets a test in `tests/Feature/Isolation`.
- **Never trust client IDs.** Re-fetch through scoped queries and authorise inside every Livewire action and controller method.
- **Money:** `DECIMAL(18,2)`, FX rates `DECIMAL(18,6)`. Use `Brick\Math\BigDecimal` via `MoneyCast`. Never use floats. Rounding is `RoundingMode::HalfUp` (brick/math 1.x enum).
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
  - The User model is `App\Domain\Identity\Models\User` (there is no `app/Models`).
  - Models use Laravel 13 attributes: `#[Fillable]`, `#[Hidden]`, `#[UseFactory]`.
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

## Key mechanics (Phase 1)
- **Current entity:** `App\Domain\Core\Support\CurrentEntity` (scoped singleton). `EnsureEntitySelected` sets it from `session('entity_id')` and also calls `setPermissionsTeamId()`. In jobs, commands and seeders, use `app(CurrentEntity::class)->run($entity, fn () => ...)`.
- **Middleware order matters:** `IdleTimeout` and `EnsureEntitySelected` are placed before `Authenticate` in the priority list (`bootstrap/app.php`), because Filament's `canAccessPanel()` needs the permission team. Both are also Livewire persistent middleware, so Livewire actions see the entity.
- **Permissions vs roles:** the permission catalogue and default roles live in `config/pace.php`. `ProvisionEntityRoles` creates the roles for a new entity. Roles are per entity (`roles.team_id` = entity id). Group Super Admin is the `users.is_group_super_admin` flag, not a Spatie role.
- **`Gate::before`** lets super admins pass everything except destructive abilities (`delete`, `restore`, …), which always go to the policy.
- **Security-sensitive user columns** (status, lockout, password, super-admin flag) are not fillable. Only the Identity actions change them, with `forceFill` plus an audit entry.
- **Audit entries** get entity, IP, user agent and request ID automatically (`AppServiceProvider::enrichAuditRecords`). Pass `withProperties(['entity_id' => …])` when the entity differs from the current one.
- **Filament resources** override `getEloquentQuery()` to scope to the current entity. Remove the generated Delete actions unless the policy explicitly allows deletes.
- **Avatars** use `InitialsAvatarProvider` (inline SVG). Never load external images, fonts or scripts (CSP).

## Key mechanics (Phase 2)
- **Master data models** (`app/Domain/MasterData/Models`) use the `IsMasterData` trait (entity scope, `is_active`, optional effective dates with `scopeUsableOn`, audit log name `master_data`). There are no deletes anywhere: deactivate, and FKs RESTRICT.
- **Admin screens** extend `App\Filament\Admin\MasterData\MasterDataResource`, with a `ManageMasterData` page (modal create and edit). Override `createRecord()`/`updateRecord()` for extra work (see BoardPaperResource). One `MasterDataPolicy` covers them all; `VendorPolicy` also lets payment roles edit bank details only.
- **Money in forms:** never `->numeric()` on money or rate inputs (it produces floats). Use a string input with a regex rule; `DecimalCast` refuses floats. brick/math 1.x uses `RoundingMode::HalfUp`.
- **Vendor bank details:** `encrypted` casts, `#[Hidden]`, excluded from the attribute audit, `bank_details_changed` events with masked values. They are only filled into forms and exports for `vendors.view_bank_details`.
- **Files:** `StoreAttachment` is the only way in: allow-list, `finfo` sniffing plus ZIP/OLE signatures, size setting, SHA-256, `AttachmentScanner` hook, random path on the `documents` disk. The only way out is `attachments.download` (entity scope, `AttachmentPolicy` → parent's `view`, audited).
- **Excel import/export:** add an `ImportDefinition` (usually a `CodeKeyedDefinition`) and register it in `ImportRegistry`; `ExcelActions::for($type)` adds the buttons. Imports are queued (`ProcessImport`) and **all-or-nothing**, with an error report of the uploaded rows plus row number and errors. Exports use the import layout (round-trip).
- **Currencies** are group-wide (string PK `code`); entities enable them through `entity_currency`, and the base currency is always enabled. Use `Currency::optionsFor($entity)`. FX is `ExchangeRateProvider::rate($entity, $from, $to, $date)` (latest effective, or the inverse pair).
- **New permissions for existing entities:** add them to `config/pace.php` and ship a data migration that calls `ProvisionEntityRoles::grantNewDefaults([...])`.

## Commands
```bash
./vendor/bin/sail up -d                 # start app, MySQL, Mailpit
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan queue:work    # queue worker
./vendor/bin/sail artisan schedule:work # scheduler (SLA reminders)
./vendor/bin/sail npm run dev
./vendor/bin/pint --test                # formatting
./vendor/bin/phpstan analyse            # Larastan
./vendor/bin/pest --parallel            # tests
composer check                          # lint + analyse + test
```
- `laravel/pao` rewrites tool output as JSON when it detects an AI agent. Prefix commands with `PAO_DISABLE=1` for normal output.
- Tests need a MySQL database named `testing` (Sail creates it). In the cloud dev container: `docker run -d --name pace-mysql -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=pace -e MYSQL_USER=pace -e MYSQL_PASSWORD=secret -p 3306:3306 mysql:9.7`, then create `testing` and grant `pace` access to `testing` and `testing_%`.
- Test helpers in `tests/Pest.php`: `kenya()`, `makeEntity()`, `userIn($entity, [roles])`, `actingInEntity($user, $entity)`, `setting($key, $value)`.

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

| 2026-09-25 | Phase 1: Group Super Admin is a user flag (Spatie teams cannot hold a global role assignment cleanly). |
| 2026-09-25 | Phase 1: sign-in uses PACE's own controllers (no Breeze or Fortify); Filament has no login page and shares the session and entity. |
| 2026-09-25 | Phase 1: the idle timeout is a setting enforced by middleware; `SESSION_LIFETIME=480` is only the outer bound. |
| 2026-09-25 | Phase 1: bulk user import from Excel moves to Phase 2, together with the generic Excel import pipeline. |

| 2026-09-25 | Phase 2: imports are all-or-nothing (finance master data must not be half-loaded); the error report lists every problem by Excel row. |
| 2026-09-25 | Phase 2: payment roles (vendors.view_bank_details) maintain vendor bank details; other vendor fields need masterdata.manage. |
| 2026-09-25 | Phase 2: group (USD reporting) exchange rates move to Phase 6 with the group dashboard; entity rates are done. |
| 2026-09-25 | After Phase 1: Kenya uses QuickBooks (procurement and shipments are standalone systems). Look and feel: professional with pastel colours; interactive dashboards and a strong home screen in Phase 6. |

Still open: hosting target (explain options when needed; Azure recommended), QuickBooks edition, M365 mailbox (**remind the user near roll-out**), logo (PLAN.md §10).

## Look and feel
- Brand colour `brand-*` (slate-blue #4f5db8 at 600) for buttons, links and focus. Pastel tints `pastel-{sky,mint,lavender,peach,rose}` with matching `-ink` text colours for cards, badges and charts.
- Light sidebar, white cards, `rounded-2xl`, soft borders. Filament primary is `Color::hex('#4f5db8')`.
