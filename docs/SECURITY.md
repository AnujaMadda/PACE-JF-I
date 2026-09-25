# PACE security notes

This document grows each phase. Phase 7 turns it into the full security review against brief §14 and pen-test readiness.

## Entity isolation (Phase 1)
- `BelongsToEntity` trait: every entity-owned model gets a global scope. It fails closed: with no current entity, queries return nothing and creates throw. `entity_id` is stamped from the current entity, can never be mass-assigned, and cannot change after creation.
- `CurrentEntity` is set per request from the session by `EnsureEntitySelected`. On every request, including Livewire updates (registered as persistent middleware), it re-checks that the user is active, unlocked and still allowed in that entity. Otherwise the session ends.
- Filament resources scope their queries to the current entity (users with access, the entity's roles, audit and login events). Records outside the scope resolve to **404**.
- Policies check permission (per entity, through Spatie teams) and entity membership. `Gate::before` gives Group Super Admins every ability **except** destructive ones (delete, restore and so on), which always go to the policy.
- Tests: `tests/Feature/Isolation`.

## Authentication
- Sign-in is one step (email, password, entity) with one generic failure message. The real reason is kept in `login_events`. Unknown emails still run a bcrypt check, so response times stay similar.
- Lockout after N failures for M minutes (settings). Admin lock and unlock, both audited.
- Rate limits: 10 sign-in attempts per minute per email+IP and 30 per IP. Sign-up and forgot-password: 3 per minute and 20 per hour.
- Activation links are signed and time-limited. They are bound to the latest issue time, so re-sending invalidates older links, and they cannot be reused once the account is active.
- Passwords: minimum 12 characters, mixed case, numbers, symbols, and an optional breached-password check (k-anonymity API). The last 5 cannot be reused. Expiry is optional.
- The session ID is regenerated on login and on entity switch. There is an idle timeout. Admins can end all of a user's sessions.

## Audit trail
- `activity_log`, `login_events`, and in later phases `budget_ledger_entries` and `workflow_actions`, are **append-only** in the application: the models throw on update or delete.
- Every entry carries `entity_id`, IP, user agent and request ID (`X-Request-Id`).
- Do **not** schedule `activitylog:clean`. Retention purges, once agreed, run as a separate privileged job.

### Database grants (production)
Use two MySQL users:

```sql
-- Migration / deploy user (used only by `php artisan migrate` during deploys)
CREATE USER 'pace_migrate'@'%' IDENTIFIED BY '…';
GRANT ALL PRIVILEGES ON pace.* TO 'pace_migrate'@'%';

-- Application user (used by web, queue and scheduler): privileges per table.
CREATE USER 'pace_app'@'%' IDENTIFIED BY '…';
GRANT SELECT, INSERT, UPDATE, DELETE ON pace.users TO 'pace_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON pace.entities TO 'pace_app'@'%';
-- … repeat for every normal table …

-- Append-only tables: SELECT and INSERT only.
GRANT SELECT, INSERT ON pace.activity_log TO 'pace_app'@'%';
GRANT SELECT, INSERT ON pace.login_events TO 'pace_app'@'%';
```

Grants are per table because MySQL cannot revoke a table privilege from a user who holds it at database level. A helper script that generates these grants from the schema is planned for Phase 7.

## HTTP security headers
The `SecurityHeaders` middleware (global) sets: CSP, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, and HSTS on HTTPS.

The CSP allows only same-origin resources. `script-src` currently includes `'unsafe-inline' 'unsafe-eval'` because Alpine.js, used by Livewire and Filament, evaluates expressions at runtime. Tightening this (nonces, CSP-safe Alpine) is a Phase 7 task. No external fonts, avatars or CDNs are loaded.

## Known follow-ups
- Phase 2: file upload hardening (MIME sniffing, size limits, scanner hook, authorised downloads).
- Phase 7: CSP tightening, grants script, dependency audit, pen-test checklist, data-retention jobs.
