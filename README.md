# PACE — Payment Approval and Control Engine

*Approvals at PACE.* JF&I Packaging's cloud platform for payment approvals. The first release covers **Capex** for the **Kenya** entity. UAE and Bangladesh will be added through configuration.

- Plan, architecture and phases: [`PLAN.md`](PLAN.md)
- Project conventions for contributors and AI sessions: [`CLAUDE.md`](CLAUDE.md)
- Original brief: [`docs/BRIEF.md`](docs/BRIEF.md)
- Security notes: [`docs/SECURITY.md`](docs/SECURITY.md)

**Status:** Phases 1 and 2 are complete.
- Phase 1 (Foundation): entities, sign-in with entity selection, activation, password and lockout policy, roles per entity, the admin panel and the audit log.
- Phase 2 (Master data): departments, cost and profit centres, GL accounts, internal orders, vendors (bank details encrypted and masked), payment terms, budget codes, Capex categories, board papers with documents, currencies and exchange rates. It also adds Excel import, export and templates, and bulk user import.

Budgets come next (Phase 3).

## Stack
Laravel 13 · PHP 8.5 (8.4+ supported) · MySQL 9.7 LTS (8.4 compatible) · Livewire 4 · Filament 5 · Tailwind CSS 4 · spatie/laravel-permission 8 (teams = entities) · spatie/laravel-activitylog 5 · Pest 5 · Larastan 3 · Pint.

## Local setup (Laravel Sail / Docker)

Requirements: Docker Desktop (or Docker Engine), and PHP 8.4+ with Composer for the first install.

```bash
git clone <repo> pace && cd pace
composer install
cp .env.example .env
./vendor/bin/sail up -d            # app, MySQL 9.7, Mailpit, Redis
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build   # or: npm run dev
```

- App: http://localhost. Admin panel: http://localhost/admin
- Mailpit, which catches all outgoing mail locally: http://localhost:8025

Run these in separate terminals while developing:

```bash
./vendor/bin/sail artisan queue:work     # mail, notifications and Excel imports are queued
./vendor/bin/sail artisan schedule:work  # scheduler (SLA reminders from Phase 6)
```

### Demo accounts (local only)

`DemoUsersSeeder` creates one account per role in Kenya, and `KenyaMasterDataSeeder` loads **sample** master data (illustrative codes, vendors and rates). It **refuses to run in production**. Every account uses the password in `DEMO_PASSWORD` (default `Pace-Demo-2026!`). Sign in with the entity **JF&I Packaging Kenya**.

| Email | Role |
|---|---|
| superadmin@jfi.lk | Group Super Admin (all entities) |
| ke.admin@jfi.lk | Entity Admin |
| ke.requester@jfi.lk | Requester |
| ke.approver@jfi.lk | Approver |
| ke.budget@jfi.lk | Budget Approver |
| ke.validator@jfi.lk | Validator |
| ke.validation@jfi.lk | Validation Approver |
| ke.coordinator@jfi.lk | Coordinator |
| ke.purchasing@jfi.lk | Purchasing |
| ke.authoriser@jfi.lk | Additional Authoriser |
| ke.payments@jfi.lk | Payment Team |
| ke.payments.manager@jfi.lk | Payment Team Manager |
| ke.auditor@jfi.lk | Viewer / Auditor |
| ho.finance@jfi.lk | Approver (head-office user with access to Kenya) |

## Quality checks

```bash
composer lint       # Pint (Laravel preset)
composer analyse    # Larastan level 6
composer test       # Pest, on MySQL (database "testing")
composer check      # all three
./vendor/bin/pest --parallel
```

Tests run against MySQL, not SQLite, so locking and JSON behaviour match production. Sail creates the `testing` database automatically. CI runs the same checks on MySQL 9.7 (`.github/workflows/ci.yml`).

## Environment variables (main ones)

| Variable | Purpose |
|---|---|
| `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_KEY` | Standard Laravel. Production: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS URL. |
| `DB_*` | MySQL connection. Use a least-privilege app user (see `docs/SECURITY.md`). |
| `SESSION_DRIVER=database` | Needed so admins can end sessions ("Sign out everywhere"). |
| `SESSION_LIFETIME` | Outer bound in minutes (480). The enforced idle timeout is the `security.session_idle_minutes` setting (default 30). |
| `SESSION_SECURE_COOKIE` | `true` in production (HTTPS only). |
| `QUEUE_CONNECTION` | `database` now; Redis-ready. |
| `MAIL_*` | Mailpit locally. The production transport is decided in Phase 6 (Microsoft Graph recommended; see PLAN.md §10). |
| `DEMO_PASSWORD` | Local demo accounts only. |

Business settings such as password policy, lockout, idle timeout and self-registration are **not** environment variables. A Group Super Admin edits them in **Admin → System settings**, and every change is audited.

## Deployment, backups

Full deployment and backup/restore documentation is a Phase 7 deliverable. In short, production needs:
- a web tier (Nginx + PHP-FPM 8.5)
- a queue worker (`php artisan queue:work`, kept alive by Supervisor or systemd)
- the scheduler (`* * * * * php artisan schedule:run`)
- MySQL 9.7 or 8.4
- private file storage (S3-compatible)
- HTTPS
