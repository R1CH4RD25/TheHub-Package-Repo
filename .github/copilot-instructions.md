# The Hub – AI Agent Guide
## Orientation
- `public/` serves every page; always bootstrap with `src/bootstrap.php` for env, session, CSRF, helpers.
- Core classes live under `src/` (PSR-4 `Hub\*`); use `Database::getInstance()` for PDO.
- Domain logic is documented in `docs/` (`INVITATION_SYSTEM.md`, `MODULAR_ARCHITECTURE.md`, `AUDIT_LOGGING.md`)—scan before changing flows.
- Web assets sit in `public/assets/{js,css}`; no JS frameworks, only vanilla scripts.
## Auth & Roles
- `src/Auth.php` handles Google OAuth, domain lock, invitation tokens, and optional Google Groups auto-approval (service account JSON in `config/`).
- Global role lives in `users.role`; additional roles in `user_global_roles`; use `Auth::getEffectiveRole()` if super admin “view as” is active.
- Sections gate via `SectionRoleAccess::hasAccess($userId, $slug)` and modules via `Module::hasAccess($userId, $slug, $minimumRole)`.
- When adding UI, check both module access (module selector) and section access (role matrix) before rendering.
## Data & Logging
- Authoritative schemas: `database/schema.sql`, `modules-schema.sql`, `sections-schema.sql`; update them first, then run `php cli/migrate.php`, `php cli/migrate-modules.php`, or `php cli/migrate-sections.php`.
- Most tables soft-delete with `is_active`; never hard delete vehicles or modules unless design demands it.
- Every mutation must call `AuditLogger` (`src/AuditLogger.php`) with before/after payloads; login/logout already logged.
- Trip purpose codes (11,23,34,36,41) in `trip_purposes` are treated as constants—do not repurpose or reorder without admin sign-off.
## Backend Patterns
- API endpoints live in `public/api/*.php`; load bootstrap, gate with `Auth::requireLogin`/`requireRole`, switch on `$_SERVER['REQUEST_METHOD']`, and respond via `jsonResponse`.
- POST/PUT/DELETE handlers must verify CSRF tokens (`verifyCsrfToken`); parse `php://input` for PUT/DELETE (`parse_str`) before using.
- Use repository classes (`FuelRecord`, `Vehicle`, `User`, `Invitation`, etc.) for database work—avoid inline SQL in controllers when a class exists.
- Long-running tasks (exports, emails) rely on Composer deps (`phpoffice/phpspreadsheet`, `phpmailer/phpmailer`); remember to include them via bootstrap.
## Frontend Conventions
- Attach JS in `public/assets/js/*.js`; wrap behavior in `document.addEventListener('DOMContentLoaded', ...)` and use Fetch + `FormData`.
- Surface feedback with the shared `showMessage(text,type)` helper (see `public/assets/js/fuel-entry.js` and `site-settings.js`).
- Admin dashboard tabs are controlled by `public/assets/js/admin.js`; extend its loaders instead of creating new scripts per tab.
- Keep markup mobile-friendly—existing CSS utilities live in `public/assets/css/`; reuse button classes (`.btn-sm`, `.btn-danger`, etc.).
## Invitations & Access Flows
- Invitation lifecycle lives in `src/Invitation.php` + `public/api/invitations.php`; tokens expire after 7 days and mark `used_at` on acceptance.
- Pending self-registrations surface via `User::getPending()` and are approved through `public/api/users.php?action=approve`; approval emails reuse the Invitation mail helpers.
- Module landing (`public/modules.php`) redirects users with a single module; ensure new modules set `base_url`, icon, and initial access in migrations.
- Section visibility is managed in the admin Sections tab—toggle matrix writes to `section_role_access`; reflects immediately in `SectionRoleAccess::getUserSections()`.
## Developer Workflow
- Install deps with `composer install`; local dev server via `cd public && php -S localhost:8000`.
- Config comes from `.env` (copied from `.env.example`); keep secrets out of source and ensure `GOOGLE_SERVICE_ACCOUNT_JSON` points to files in `config/`.
- Monitor runtime issues in `logs/php-errors.log` and session state in `sessions/`; production runs require HTTPS because cookies set `secure`.
- Before deploying, rerun migrations, clear stale sessions if auth changes, and verify admin/super admin flows including Activity Logs.
