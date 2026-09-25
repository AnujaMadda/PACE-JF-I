# PACE: Capex Module Build Brief for Claude Code

## 1. How to work on this project

You are building a cloud-based payment management platform called **PACE** (Payment Approval and Control Engine) for JF&I Packaging. This first release covers the **Capex process only**, for the **Kenya** entity, with UAE and Bangladesh to follow.

Before writing any code:

1. Check the latest stable Laravel release and the newest PHP version it supports, and confirm the MySQL target (MySQL 8.4 LTS or newer). Verify versions with Composer and the official docs rather than assuming them.
2. Read this whole brief, then create `PLAN.md` containing: architecture overview, entity relationship diagram (Mermaid), package list with versions, folder structure, and the phase plan from section 15.
3. List every question or assumption from section 17 that you need confirmed, and wait for my answers before starting Phase 1.

While working:

- Work phase by phase. At the end of each phase, stop and give me a summary of what was built, how to run and test it, and anything that needs my decision.
- Commit at the end of each phase with clear commit messages.
- Maintain a `CLAUDE.md` file with project conventions, decisions, and commands so future sessions stay consistent.
- Never hardcode entity-specific or approval-specific logic. If something differs by entity, it is configuration.
- Items marked **[CONFIRM]** are placeholders I will fill in or confirm.

## 2. Business context

- The long-term platform scope is four processes: Capex, OpEx and Other Expenses, Petty Cash and IOU, and Courier Requests. **Only Capex is in scope now.** Build the shared foundations (entities, users, master data, workflow engine, attachments, notifications, audit, dashboard) so the other three processes plug in later without restructuring.
- Older process documents refer to the legacy system as "REAP" and to an earlier concept as "Emerald X2P". Do not use either name anywhere in the new system. The product name is PACE, with the tagline "Approvals at PACE".
- **Entities:** Kenya first. UAE and Bangladesh later. Entity is a first-class concept from day one.
- **Requesters belong to the foreign operating entity** (Kenya for now). Approvers, coordinators, purchasing and finance users may belong to the same entity or be group-level users (for example head office in Sri Lanka) who are granted access to that entity.
- **There is no GL Team.** The validation the GL Team performed in the old process becomes a configurable step in the approval path, assigned to whoever the admin chooses.
- **Purchase orders and payments are executed in the entity's ERP, outside PACE.** PACE records PO and payment references and stores the documents. The source process documents mention SAP, but keep this ERP-neutral. Define a clean interface (for example an `ErpConnector` contract with a null implementation) so an ERP integration can be added later.

## 3. Tech stack

- Laravel (latest stable), PHP (latest version supported by that Laravel release), MySQL 8.4+.
- User-facing app: Blade, Livewire, Tailwind CSS.
- Admin panel: Filament (latest version compatible with the chosen Laravel release).
- If you believe a different UI choice is clearly better, say so in `PLAN.md` with reasons before building.
- Suggested packages (confirm compatibility): `spatie/laravel-permission` with teams enabled (team = entity), `spatie/laravel-activitylog`, Laravel Excel or PhpSpreadsheet for imports and exports, Pest for tests, Larastan, Laravel Pint.
- Queues for mail and heavy jobs (database driver initially, Redis-ready). Laravel scheduler for SLA reminders and escalations.
- File storage through the Laravel filesystem on a **private** disk, configurable to S3-compatible storage in production.
- Mail through SMTP settings in `.env` (the company uses Microsoft 365).
- Local development with Laravel Sail or Docker Compose. Provide deployment notes for a cloud-hosted Linux server or container platform, not provider-specific infrastructure code. Hosting target: **[CONFIRM]**.
- Store all timestamps in UTC and display them in the current entity's timezone (Kenya: `Africa/Nairobi`).
- Money columns as `DECIMAL(18,2)`, exchange rates as `DECIMAL(18,6)`. Never use floats for money.

## 4. Multi-entity model

- `entities` table: code (KE, AE, BD), name, country, base currency (KES, AED, BDT), timezone, financial year start month, request number prefix, allowed email domains, active flag.
- Single database. Every master data and transactional table carries `entity_id`.
- Enforce isolation in two layers: a global scope tied to the session's current entity, and authorization policies on every model and action.
- Write tests proving a user in entity A can never read or change entity B data, including through direct URL or ID manipulation, Livewire actions, exports, and file downloads.
- A user can be assigned to one or more entities, with roles per entity (Spatie teams).
- A Group Super Admin role works across all entities.
- Adding UAE or Bangladesh must be a configuration task (create entity, load master data, set up workflow), not a code change.

## 5. Authentication and sign-up

### Login
- Fields: company email, password, entity (dropdown of active entities).
- Validate the credentials and that the user has access to the selected entity. On failure, show one generic error message that does not reveal whether the email exists.
- Store the current entity in the session. Users assigned to several entities can switch entity from the header. Switching reloads context and is logged.

### Sign-up (activation of admin-created users)
- An admin pre-creates the user: name, company email, designation, department, entity access, roles. Status: **Invited**.
- The user opens the Sign Up page and enters their company email. If it matches an Invited user and belongs to an allowed domain, the system emails a signed, time-limited link to set a password. After setting it, status becomes **Active**.
- Emails that were not pre-created, or that fall outside the allowed domains, get a neutral message and no account is created.
- Optional setting (off by default): allow self-registration from allowed domains, landing in **Pending Admin Approval**.
- Allowed company email domain(s): **[CONFIRM]**.

### Passwords and sessions
- Laravel `Password` rules: minimum 12 characters, mixed case, numbers, symbols, breached-password check (configurable).
- No reuse of the last 5 passwords. Optional password expiry (configurable, off by default).
- Lock the account after 5 failed attempts for 15 minutes (both configurable). Admin can unlock.
- Idle session timeout of 30 minutes (configurable). Use the database session driver so admins can revoke sessions.
- Forgot password through an emailed reset link.
- Record login history: success or failure, timestamp, IP, user agent, entity.
- Design authentication so Microsoft Entra ID (Azure AD) single sign-on can be added later. Active Directory is not integrated today.

## 6. Admin panel

Two admin levels: **Group Super Admin** (all entities) and **Entity Admin** (scoped to their entities). All admin actions are audited.

- **Entities:** create and manage (super admin only).
- **Users:** create, edit, bulk import from Excel, assign entities and roles, resend invitation, send password reset link (admins never see or set passwords), lock and unlock, deactivate and reactivate, force logout, view login history, set a delegate.
- **Roles and permissions:** view and edit permission sets per role.
- **Master data:** CRUD plus Excel import and export, with a row-level validation error report on import.
- **Budgets** (section 9).
- **Workflow designer and simulator** (section 8).
- **System settings:** allowed email domains, password and lockout policy, session timeout, SLA defaults, attachment types and size limits, minimum quotation count, request number format, PO and invoice tolerances.
- **Audit log viewer** with filters and export.

## 7. Master data (per entity)

From the source process documents: Cost Centres, Profit Centres, GL Accounts, Internal Orders, Vendors, Payment Terms, Users, Budget Codes, Board Paper References.

Also add: Departments (with Head of Department), Currencies, Exchange Rates (effective-dated), Capex Categories or Asset Classes.

Rules:
- Common fields: code, name, active flag, effective dates where relevant.
- Deactivate only. Never hard delete a record that is referenced.
- GL Accounts carry a type flag (Capex, OpEx, and so on) so the Capex form only offers Capex accounts.
- Cost Centres have an owner user and a linked department.
- Vendors: bank details encrypted at rest (encrypted casts) and masked in the UI except for payment roles.
- Board Papers: reference number, date, approved amount, description, attachment.

## 8. Configurable approval workflow engine (core of the system)

Admins define approval paths in the system. No approval logic is hardcoded.

### Workflow definitions
- Fields: entity, process type (`capex` now, others later), name, version, status (Draft, Active, Retired), effective from date, priority.
- Editing an active definition creates a new version. In-flight requests keep a snapshot of the version they were submitted under.
- Several definitions per entity and process are allowed, with selection rules (for example by category, department or amount band). If more than one matches, priority decides.

### Steps (ordered)
Each step has:
- **Name:** shown to users as the request's current status (for example "Pending Coordinator").
- **Type:** Approval, Validation or Review, Action (a task must be completed, such as Upload PO, Upload Invoice, Record Payment), or Information (notify only).
- **Assignee rule:** specific user(s); a role within the entity; Head of Department of the request's department; owner of the request's cost centre; the requester's line manager (if set on the user); or a user chosen by the previous step's actor from an allowed list.
- **Mode:** any one assignee, or all assignees (parallel).
- **Conditions** that decide whether the step applies: amount in base currency (min and max), budget status (Within Budget, Exceeds Budget, No Budget), quotation count below minimum, category, department, cost centre, board paper present or not. Steps whose conditions fail are skipped, and the skip is logged.
- **Allowed actions:** Approve, Reject, Return to Requester, Return to Previous Step, Request Information. A comment is mandatory for Reject and any Return.
- **Editable fields at the step:** for example the validator can correct the GL account, or the coordinator can confirm the vendor. Every change is audited with before and after values.
- **SLA hours, reminder frequency, escalation user or role.**

### Segregation of duties (enforced by the engine)
- A requester can never approve or validate their own request.
- The same person cannot act on two approval steps of the same request, unless an admin explicitly allows it at step level.
- The user who records payment cannot be the final approver (configurable as block or warning, default block).

### Other rules
- If a step's assignee rule resolves to nobody, block submission with a clear message to the requester and notify the entity admin.
- **Delegation:** a user sets a delegate and a date range. Admins can reassign any pending step. The audit trail shows "approved by X on behalf of Y".
- **Simulator:** the admin enters sample values (amount, department, category, budget status, quotation count) and sees the resolved path and named assignees before activating a workflow.
- **Designer UI** in Filament: ordered, drag-to-reorder steps, and clone definition.

### Default Kenya Capex workflow (seed data)
Mirror the source process flow, using placeholder roles that the admin assigns to real users. Thresholds are sample values marked **[CONFIRM]**.

1. Request Creation (requester), with a minimum of 3 quotations attached.
2. Budget Approval: applies only when budget status is Exceeds Budget or No Budget.
3. Validation (replaces the old GL Team validation): check GL account, cost centre, budget code and documents.
4. Validation Approval (replaces the old GL Team approval).
5. Pending Coordinator.
6. Purchasing: create PO in the ERP and upload the PO to PACE (Action).
7. HoD Approval.
8. Additional Authorisation: applies only above a configurable amount **[CONFIRM]**.
9. Invoice Upload and Approved Invoice List (Action).
10. Payment Team Manager review.
11. Payment Processing: record payment reference, date, amount and bank transfer reference (Action).
12. Completed.

## 9. Budgets

- Budgets are finalised annually and allocated across 12 months.
- Budget record: entity, financial year, budget code, cost centre, annual amount, and 12 monthly allocations.
- Financial year start month is set per entity. Kenya: **[CONFIRM]**.
- Available budget = allocation (annual or year-to-date, per entity setting **[CONFIRM]**) minus committed minus actual.
- Budget ledger (append-only) with entry types: Commitment, Release, Adjustment, Actual, Transfer.
  - Reserve on submission or on final approval (setting).
  - Release on reject or cancel.
  - Adjust to the PO value when the PO is uploaded.
  - Convert to actual when payment is recorded.
- Budget import from Excel.

## 10. Capex request lifecycle

### Request form
- **Automatic:** request number (for example `KE-CPX-FY27-00001`, per entity and per financial year, gap-free and safe under concurrent submissions), entity, requester, request date.
- **Fields:** department, cost centre, profit centre, GL account (Capex type only), internal order (optional), budget code, capex category, board paper reference (optional or required by rule), title, description, business justification, required-by date, currency (defaults to entity base currency), exchange rate (auto-filled from the rates table, editable only with permission), amount, base currency amount (calculated).
- **Line items (optional):** description, quantity, unit, unit price, total. When lines are used, the header amount equals their sum.
- **Budget panel:** shows allocation, committed, actual and available live on the form.
- **Quotations:** minimum 3 (configurable per entity and category). Each quotation: vendor (from master, or a new vendor name flagged for creation), quote reference, date, amount, currency, validity date, file. The requester marks the recommended quotation with a reason. If fewer than the minimum are attached, a sole-source justification is required and the "quotation count below minimum" condition becomes available to the workflow.
- **Supporting documents:** typed attachments (specification, board paper, other).
- Save as Draft, then Submit.

### Submission
- Run the budget check, store the result as a snapshot on the request (Within Budget, Exceeds Budget, No Budget), and use it for workflow conditions.
- Resolve the workflow, validate that every applicable step has an assignee, then start the first step.

### Statuses
Draft, Submitted, In Progress (with the current step name displayed), Returned, Rejected, Cancelled, Completed. Cancel is allowed by the requester before the first approval, or by an admin with a reason. Each request shows a step-by-step timeline with actor, action, comment and timestamp.

### Purchase order
- Fields: PO number, date, amount, currency, vendor, PDF.
- If the PO amount exceeds the approved amount beyond a configurable tolerance percentage, route through a configured re-approval step.

### Invoices
- Multiple invoices per request (advances, milestones).
- Fields: invoice number, date, amount, tax, file.
- Duplicate check: invoice number unique per vendor per entity.
- Total invoiced cannot exceed the PO amount plus tolerance without an audited override.

### Payments
- Multiple payments, each linked to an invoice: payment reference, date, amount, method, bank reference.
- The request completes when paid in full, or when Finance closes it manually with a reason.

### Returned requests
- The requester edits and resubmits. The workflow restarts from the beginning by default (configurable per workflow). Previous history is kept.

### Closure
- Completed requests become read-only, archived and searchable for audit, reporting and tracking.

## 11. Roles (initial set)

Group Super Admin, Entity Admin, Requester, Approver (generic, used by workflow steps), Validator, Coordinator, Purchasing, Payment Team, Payment Team Manager, Viewer or Auditor (read-only across the entity). Roles are per entity. Admins can create more.

## 12. Notifications

- Email plus in-app notifications (database notifications with a bell icon).
- Events: assigned to you, approved, rejected, returned, request information, completed, SLA reminder, escalation, delegation set or used.
- Emails contain a short summary and a deep link that requires login. No approve-by-email in this phase.
- All mail is queued.

## 13. Dashboard and reports

### Layout
Follow the reference dashboard layout from the source process documents (if I share the PDF, use it as a visual reference only): left sidebar with Dashboard; Request Management (Capex Requests active; OpEx, Petty Cash and Courier shown disabled as "Coming soon"); Payment Management (Payments Tracker); Master Data; Reports and Analytics; Admin (System Settings, Audit Logs). KPI cards along the top. Show the PACE name and tagline "Approvals at PACE" in the sidebar header and on the login page. Primary brand colour: **[CONFIRM]** (use a neutral professional palette until confirmed, with colours defined as Tailwind theme tokens so they are easy to change).

### Phase 1 widgets
- **Requester:** my requests by status, drafts, returned items needing action.
- **Approver:** My Pending Approvals with ageing and due date, and a quick review view.
- **Finance and management:** Total Requests, Pending Approvals, Requests in Process, Completed Requests, Total Paid Amount; Request Status Overview by step; Budget Utilisation gauge (allocated, committed, actual, available); Top Cost Centres by Spend; Recent Requests.
- **Group view (super admin):** consolidated across entities in a reporting currency (default USD, configurable **[CONFIRM]**).

### Reports (Excel export)
Capex register; pending approvals ageing; budget vs committed vs actual; approval turnaround time by step and by approver; full audit trail per request (printable PDF).

## 14. Audit and security

- **Audit trail:** every create, update, status change, approval action, login, entity switch and admin action, with user, entity, timestamp, IP and before and after values. Append-only: the application never updates or deletes audit records. Document how to restrict the database user accordingly.
- **Authorization:** policies on every route and Livewire action. Never trust IDs sent from the client.
- **File uploads:** allowlist of types (PDF, JPG, PNG, XLSX, DOCX, MSG), configurable size limit (default 10 MB), private storage with random file names, SHA-256 hash stored, downloads only through an authorised controller. Leave a hook for antivirus scanning.
- **OWASP Top 10:** CSRF protection, output escaping, mass assignment protection, query bindings only, rate limiting on auth endpoints, security headers (CSP, HSTS, X-Frame-Options, Referrer-Policy), secure and HttpOnly cookies, HTTPS only in production, debug off in production, secrets only in `.env`.
- **Data protection:** collect only the personal data needed. Consider the Kenya Data Protection Act 2019 and Sri Lanka's PDPA. Configurable retention periods.
- **Backups:** document daily MySQL and file storage backups and a restore test procedure.
- Assume the system will go through a third-party vulnerability assessment and penetration test before go-live.

## 15. Phases

- **Phase 0, Plan:** no code. Deliver `PLAN.md`, `CLAUDE.md` and the questions from section 17.
- **Phase 1, Foundation:** project setup, entities, authentication with entity selection, sign-up and activation, password and lockout policy, roles and permissions, admin panel for users and entities, audit logging base.
- **Phase 2, Master data:** all master data with CRUD and Excel import and export.
- **Phase 3, Budgets:** budgets, monthly allocations, ledger, availability calculation.
- **Phase 4, Workflow engine:** definitions, versioning, steps, conditions, assignee resolution, segregation of duties, delegation, designer UI, simulator, default Kenya Capex workflow seed.
- **Phase 5, Capex lifecycle:** request form, quotations, submission, approvals, PO, invoices, payments, returns, cancellation, closure.
- **Phase 6, Visibility:** notifications, SLA reminders and escalation, dashboards, reports.
- **Phase 7, Hardening:** security review against section 14, full test pass, performance check with realistic data volumes, README and deployment documentation.

## 16. Quality requirements

- Pest feature tests covering at least: login with entity selection, activation flow, lockout, entity isolation, workflow resolution (conditions, skipping, segregation of duties, unresolved assignee), budget calculation and ledger, full Capex happy path, return and resubmit, reject, delegation, PO tolerance, duplicate invoice detection.
- Seeders: Kenya entity, sample master data, one demo user per role (credentials documented for local use only), default workflow, sample budget.
- Laravel Pint formatting and Larastan level 6 or higher.
- README covering setup, environment variables, queue worker, scheduler, deployment, and backup.

## 17. Assumptions to confirm in Phase 0

1. Users are pre-created by an admin and activate themselves through Sign Up.
2. Allowed company email domain(s).
3. Financial year start month for Kenya.
4. Budget availability checked on annual or year-to-date allocation.
5. Budget commitment on submission or on final approval.
6. Approval thresholds, including the Additional Authorisation amount.
7. Position of HoD Approval: the source flow places it after PO upload. Confirm, or move it before Pending Coordinator (configurable either way).
8. Which ERP the Kenya entity uses for POs and payments, for the future connector.
9. Reporting currency for the group view.
10. Hosting target for production.
11. Primary brand colour for PACE.

## 18. Out of scope for this release

OpEx and Other Expenses, Petty Cash and IOU, Courier Requests, ERP integration, Microsoft Entra ID single sign-on, approve-by-email, mobile app. Design so each can be added later.
