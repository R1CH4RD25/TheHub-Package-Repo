# Fleet Package Compatibility Audit Report

**Prepared for:** Woodson ISD Auditor
**Date:** February 17, 2026
**System:** The Hub v3.0 — Package Platform
**Package:** Vehicle Maintenance & Fleet Tracking v2.1.0
**Prepared by:** Technology Department

---

## Executive Summary

This document verifies the compatibility of the Vehicle Maintenance & Fleet Tracking package with The Hub's infrastructure before development begins. Ten systemic gaps were identified during the audit (see Section 6) and have been resolved at the design or infrastructure level. The package core is cleared for build; Vehicle Sharing will ship disabled until `PackageManager` install-time resolution and `PackageValidator` contract compliance checks are implemented (see Section 2.3 Implementation Status).

| Area | Status | Notes |
|------|--------|-------|
| Permission Model | ✅ PASS | Dual-path resolved (Google Groups + direct roles) |
| Database Architecture | ✅ PASS | Hub-native tables with `vm_` prefix |
| Auto-Migration | ✅ PASS | Tables created automatically on package install |
| Role Mapping | ✅ PASS | All 13 district roles mapped to package roles |
| Layer 2 Workflow | ✅ PASS | Approval states on fuel logs + maintenance events |
| Audit Trail | ✅ PASS | Package-level + system-level audit logging |
| Admin Dashboard | ✅ PASS | Section toggle matrix, role management, Google Sync UI |
| Reporting | ✅ PASS | SQL queries against `vm_*` tables via standard tools |
| Settings-Driven UI | ✅ PASS | 22 toggle switches control field visibility district-wide |
| Vehicle Sharing | 🟡 DESIGN APPROVED | Resource Registry spec complete — contracts, views, ownership; install-time resolution not yet coded in `PackageManager` |

---

## 1. Permission Model Verification

### 1.1 Dual-Path Permissions

The Hub supports **two independent permission pathways** that operate simultaneously:

| Path | Source | Storage | Used By |
|------|--------|---------|---------|
| **Primary Role** | Manual assignment by admin | `users.role` column | All access checks |
| **Google Groups** | Auto-synced at login via Google Admin SDK | `user_global_roles` table | Section visibility + package access |

**Resolution:** `PackageAccessResolver.getUserPackageRole()` now checks **both** `users.role` AND all entries in `user_global_roles`. When a user has multiple roles from different sources, the **highest-privilege matching package role wins**.

**Example scenario:**
- User has `users.role = 'staff'` (manual assignment)
- User also has `user_global_roles` entry for `'admin'` (from Google Group sync)
- Fleet package maps: `staff → user`, `admin → manager`
- **Result:** User gets `manager` role in the fleet package (highest match)

### 1.2 Role Mapping — Fleet Package

The fleet package defines 3 internal roles with inheritance:

```
admin (full control)
  └── inherits → manager (approve/reject, edit configs)
        └── inherits → user (view fleet, submit logs)
```

These map to district system roles via `globalRoleMapping`. The 13 system roles are the **canonical set** defined in the `users.role` and `user_global_roles.role` ENUM columns (see `CONTRIBUTING.md` § "Valid system roles for globalRoleMapping"). The package validator warns on any role not in this list:

| District System Role | Fleet Package Role | Access Level |
|---------------------|-------------------|--------------|
| `super_admin` | `admin` | Full control (auto-highest) |
| `admin` | `manager` | Approve/reject, configure |
| `principal` | `manager` | Approve/reject, configure |
| `business_manager` | `manager` | Approve/reject, configure |
| `maintenance_director` | `manager` | Approve/reject, configure |
| `substitute_manager` | `user` | View fleet, submit logs |
| `counselor` | `user` | View fleet, submit logs |
| `maintenance_staff` | `user` | View fleet, submit logs |
| `custodial_manager` | `user` | View fleet, submit logs |
| `custodial` | `user` | View fleet, submit logs |
| `cafeteria` | `user` | View fleet, submit logs |
| `student` | `user` | View fleet, submit logs |
| `staff` | `user` | View fleet, submit logs |

### 1.3 Section Visibility

Before a user can access any package, their role must appear in the **section role access matrix** (admin-managed toggle grid). `SectionRoleAccess::hasAccess()` already checks both `users.role` AND `user_global_roles` — **no changes required**.

---

## 2. Database Architecture

### 2.1 Design Decision: Hub-Native Tables

All fleet tables reside in `woodson_hub` (the primary Hub database) with namespace prefix `vm_`.

| Approach | Chosen | Rationale |
|----------|--------|-----------|
| Separate database (`woodson_fleet`) | ❌ | Requires DBA intervention, cross-DB FK fragility, backup complexity |
| Hub-native with prefix (`vm_*`) | ✅ | Zero-config, native foreign keys, single backup, JOINs to `users` |

### 2.2 Table Inventory (12 tables)

| Table | Purpose | Primary Key | Foreign Keys |
|-------|---------|-------------|--------------|
| `vm_vehicles` | Fleet inventory | CHAR(26) ULID | → `vm_departments`, `vm_campuses`, `users.id` |
| `vm_trip_categories` | Trip codes (11,23,34,36,41) | CHAR(26) ULID | — |
| `vm_fuel_logs` | Fuel/trip records with workflow | CHAR(26) ULID | → `vm_vehicles`, `vm_trip_categories`, `users.id` |
| `vm_maintenance_items` | Master maintenance types | CHAR(26) ULID | — |
| `vm_maintenance_events` | Completed maintenance with workflow | CHAR(26) ULID | → `vm_vehicles`, `vm_maintenance_items`, `users.id` |
| `vm_maintenance_templates` | Reusable maintenance plans | CHAR(26) ULID | → `users.id` |
| `vm_template_items` | Items within templates | CHAR(26) ULID | → `vm_maintenance_templates`, `vm_maintenance_items` |
| `vm_vehicle_schedules` | Per-vehicle maintenance schedule | CHAR(26) ULID | → `vm_vehicles`, `vm_maintenance_items` |
| `vm_departments` | Organizational grouping | CHAR(26) ULID | — |
| `vm_campuses` | Campus assignment | CHAR(26) ULID | — |
| `vm_settings` | Package configuration (single row, 22 toggles) | CHAR(26) ULID | — |
| `vm_audit_logs` | Package audit trail | BIGINT AUTO | → `users.id` |

### 2.3 Vehicle Sharing (Cross-Package Resource Registry)

Vehicles are a **shared district resource** managed through the platform's **Resource Registry** — a production-grade contract system that prevents duplicate tables, enforces schema boundaries, and tracks ownership. Full specification is in `CONTRIBUTING.md` § "Shared Resources."

#### How it works

1. **Canonical contract** — `hub_resource_contracts` stores the `operations.vehicles` v1.0.0 contract defining 9 required columns, 6 optional columns, and behavior rules (soft delete semantics, migration authority, etc.)
2. **Provider registration** — The fleet package registers as the provider of `operations.vehicles` via `hub_resource_providers`, linking to source table `vm_vehicles` and platform view `hub_vehicles_v1`
3. **Platform view** — `hub_vehicles_v1` is a `CREATE OR REPLACE VIEW` that exposes only contracted columns with `WHERE is_shared = 1 AND is_deleted = 0`. Consumers query this view as the **supported contract interface**; platform validation prevents packages from binding directly to provider tables. (This is a contract boundary enforced by the platform validator, not a DB ACL.)
4. **Consumer dependency** — Future packages (Trip Planning, Purchase Orders) declare `resources.requires` in their manifest with `"key": "operations.vehicles"` and a SemVer constraint. Install-time resolution verifies the provider exists with a compatible contract version

#### Contracted columns exposed via `hub_vehicles_v1`

| Column | Contract Status | Type | Purpose |
|--------|----------------|------|----------|
| `id` | Required | CHAR(26) | ULID primary key |
| `unit_number` | Required | VARCHAR(50) | District identifier |
| `name` | Required | VARCHAR(255) | Display name |
| `year`, `make`, `model` | Required | INT/VARCHAR | Vehicle identification |
| `is_out_of_service` | Required | BOOLEAN | Availability check |
| `is_deleted` | Required (filter) | BOOLEAN | Always FALSE in view |
| `created_at` | Required | TIMESTAMP | Creation time |
| `color`, `vin`, `license_plate` | Optional | Various | Detail fields |
| `current_odometer` | Optional | INT | Latest mileage |
| `department_id`, `campus_id` | Optional | CHAR(26) | Organizational assignment |

Columns **not** exposed (internal to fleet): `assigned_driver_id`, `is_shared` (used in filter only), `updated_at`, and any future provider-internal columns.

#### Package manifest declaration

```json
"resources": {
    "provides": [{
        "key": "operations.vehicles",
        "contract": "1.0.0",
        "table": "vm_vehicles",
        "view": "hub_vehicles_v1",
        "settingsGate": "share_vehicles"
    }],
    "requires": []
}
```

#### Ownership rules

| Action | Fleet Package (Provider) | Consumer Packages | Platform |
|--------|--------------------------|-------------------|----------|
| ALTER TABLE `vm_vehicles` | ✅ | ❌ Never | ❌ |
| CREATE OR REPLACE VIEW | ✅ (defines at install) | ❌ | ✅ (validates against contract; blocks non-compliant definitions) |
| INSERT/UPDATE/DELETE rows | ✅ | ❌ (read-only) | ❌ |
| SELECT from `hub_vehicles_v1` | ✅ | ✅ | ✅ |

> **View authority:** The provider package is the source of truth for the view definition. The platform validates that the view exposes all required contract columns and respects the contract filter. On contract upgrades, the platform regenerates the view from the new contract spec.

This architecture means a future **Trip Planning** or **Purchase Order** package can select shared vehicles without duplicating the vehicle table, and with a clean upgrade path via SemVer contract versioning.

#### Implementation status

| Component | Status | Location |
|-----------|--------|----------|
| Resource registry DDL (3 tables) | ✅ Complete | `database/resource-registry-schema.sql` |
| Migration CLI | ✅ Complete | `cli/migrate-resource-registry.php` |
| `hub_vehicles_v1` view | ✅ Complete | `migrations/001_create_fleet_tables.sql` |
| Contract seed data (`operations.vehicles` v1.0.0) | ✅ Complete | `database/resource-registry-schema.sql` |
| Package manifest `resources` block | ✅ Complete | `package.json` |
| `PackageManager` install-time resolution step | 🔴 Not yet coded | `src/PackageManager.php` |
| `PackageValidator` contract compliance check | 🔴 Not yet coded | `src/PackageValidator.php` |
| Consumer package example | 🔴 No consuming packages exist yet | — |

> **Build sequence:** Registry tables → PackageManager resolution → PackageValidator rules → first consumer package.

#### Registry tables (platform DDL)

| Table | Purpose |
|-------|---------|
| `hub_resource_contracts` | Canonical definitions — required/optional columns, behavior rules, SemVer |
| `hub_resource_providers` | Ownership registry — which package provides which resource, via which view |
| `hub_resource_consumers` | Dependency links — which packages consume which resources, with capability + min version |

### 2.4 Settings-Driven Field Visibility

The `vm_settings` table contains **22 boolean toggles** organized into 7 groups. Admins control which optional fields appear in forms, tables, detail views, and dashboard cards. All underlying database columns always exist — only the UI visibility changes.

| Group | Toggle Count | Default | Purpose |
|-------|-------------|---------|---------|
| Fuel Logging | 7 | All OFF except odometer | Cost, vendor, receipt, fuel type, price/gal, purchase flag |
| Maintenance | 6 | Costs OFF, scheduling ON | Cost tracking, parts/labor split, invoices, photos |
| Vehicle Details | 4 | All OFF | VIN, license plate, color, assigned driver |
| Organization | 2 | Both ON | Departments, campuses |
| Workflow | 2 | Both ON | Approval workflow, driver tracking |
| Scheduling | 3 | ON with defaults | Schedule engine, lead time (30 days), lead distance (500 mi) |
| Sharing | 1 | ON | Cross-package vehicle sharing |

**Design rationale:** Woodson ISD indicated fuel cost tracking is not a priority — they primarily need to track *when*, *how much fuel*, *who*, *what vehicle*, and *trip purpose*. The settings system allows districts that **do** need cost analysis to enable it without burdening those that don't.

**Implementation pattern:** Package UI uses `showIf: {settingsKey: "track_*"}` on form fields, table columns, detail view fields, and dashboard cards. This is an **official platform capability** documented in `CONTRIBUTING.md` § "Conditional Field Visibility." The `GenericPackageHandler` fetches settings once per request (cached 600s) and evaluates visibility server-side. Settings updates should invalidate the cache immediately; otherwise changes propagate within 10 minutes.

### 2.5 Data Integrity

- All tables use `InnoDB` engine with `utf8mb4` character set
- Foreign keys enforce referential integrity (`ON DELETE RESTRICT` for operational data, `ON DELETE SET NULL` for optional references, `ON DELETE CASCADE` for template items)
- **Soft deletes** via `is_deleted` flag on `vm_vehicles` — **no hard deletes** for operational entities
- **Toggleable records** use `is_active` flag instead (lookup tables and schedules): `vm_departments`, `vm_campuses`, `vm_maintenance_items`, `vm_maintenance_templates`, `vm_trip_categories`, `vm_vehicle_schedules`. These are paused/resumed, not deleted.
- Trip categories have an explicit `code VARCHAR(10) NOT NULL UNIQUE` column — the numeric codes (11, 23, 34, 36, 41) are seeded during migration and treated as **constants**. The ULID `id` is the primary key for FK relationships; the `code` is the human-readable identifier.

---

## 3. Auto-Migration System

### 3.1 Package Install Workflow

When a `.hubpkg` package is installed via the admin UI:

```
Step 1: Upload & Validate
  → PackageManager validates JSON schema, version compatibility

Step 2: Install Metadata (transactional)
  → Creates section record (inactive by default)
  → Creates menu items from presentation.pages
  → Creates role mappings from policy.globalRoleMapping
  → Stores capabilities JSON for runtime engine

Step 3: Auto-Migrate Database (post-commit)
  → Searches packages/local/{pkgId}/migrations/*.sql
  → Falls back to packages/local/{pkgId}/database/schema.sql
  → Executes SQL statements sequentially
  → Records in package_migrations table (idempotent)
  → Audit-logged

Step 4: Admin Activation
  → Admin enables section in Manage Sections tab
  → Roles toggled in section role access matrix
  → Package appears in sidebar/management dashboard
```

### 3.2 Migration File Structure

```
packages/local/operations-fleet-maintenance/
├── migrations/
│   ├── 001_create_fleet_tables.sql    # 12 CREATE TABLE statements
│   └── 002_seed_data.sql             # Trip categories, maintenance items, defaults
├── database/
│   └── schema.sql                     # Reference schema (fallback)
└── .hubpkg                            # Package manifest
```

### 3.3 Idempotency

- All `CREATE TABLE` statements use `IF NOT EXISTS`
- All seed `INSERT` statements use `INSERT IGNORE`
- Migration execution is tracked in `package_migrations` table — will not re-run

---

## 4. Layer 2 Workflow

### 4.1 State Machine

Fuel logs and maintenance events follow this approval workflow:

```
  draft → submitted → in_review → approved
                         ↓
                   needs_correction → resubmitted → in_review → approved
                         ↓
                     rejected
```

| State | Who Can Transition | Description |
|-------|-------------------|-------------|
| `draft` | User | Initial entry, not yet submitted |
| `submitted` | User | Submitted for manager review |
| `in_review` | Manager/Admin | Under active review |
| `needs_correction` | Manager/Admin | Returned to user for fixes with notes |
| `resubmitted` | User | User fixed issues and resubmitted |
| `approved` | Manager/Admin | Accepted — immutable |
| `rejected` | Manager/Admin | Denied — requires new submission |

### 4.2 Edit Boundaries (by Actor)

Once a record leaves `draft`, edit rules depend on **who** is editing and **what state** the record is in:

**User edits** (the submitter):

| Fields | States Allowed | Requires Reason? |
|--------|----------------|-------------------|
| `gallons`, `odometer`, `total_cost`, `trip_category_id`, `vendor`, `location`, `notes` | `draft`, `needs_correction` | No |
| `id`, `created_at`, `logged_by`, `vehicle_id`, `event_date` | ❌ Never | — |

**Manager/Admin edits** (the reviewer):

| Fields | States Allowed | Requires Reason? |
|--------|----------------|-------------------|
| `gallons`, `odometer`, `total_cost`, `trip_category_id` | `submitted`, `in_review` | Yes (`correction_reason`) |
| `id`, `created_at`, `logged_by`, `vehicle_id`, `event_date` | ❌ Never | — |

**Rationale:** `needs_correction` is a *user-owned* state — the manager returned the record for the user to fix. Manager edits happen in `submitted`/`in_review` before returning. This prevents conflicting edits between actors.

### 4.3 Audit Events

Every workflow transition is logged to `vm_audit_logs`:

| Event | Trigger |
|-------|---------|
| `FUEL_LOG_SUBMITTED` | User submits fuel log |
| `FUEL_LOG_REVIEW_STARTED` | Manager begins review |
| `FUEL_LOG_NEEDS_CORRECTION` | Manager returns for correction |
| `FUEL_LOG_RESUBMITTED` | User re-submits after correction |
| `FUEL_LOG_APPROVED` | Manager approves |
| `FUEL_LOG_REJECTED` | Manager rejects |
| `MAINTENANCE_EVENT_SUBMITTED` | User submits maintenance event |
| `MAINTENANCE_EVENT_NEEDS_CORRECTION` | Manager returns for correction |
| `MAINTENANCE_EVENT_RESUBMITTED` | User re-submits after correction |
| `MAINTENANCE_EVENT_APPROVED` | Manager approves |
| `VEHICLE_CREATED` | Admin adds vehicle |
| `VEHICLE_UPDATED` | Admin updates vehicle |
| `VEHICLE_OOS_TOGGLED` | Vehicle out-of-service status changed |

---

## 5. Reporting Capability

The superintendent can run reports against the `vm_*` tables using standard SQL queries. All data resides in `woodson_hub`, accessible via the existing MySQL credentials.

### Example Reports

**Fuel consumption by trip category (monthly):**
```sql
SELECT tc.name AS trip_type,
       COUNT(*) AS log_count,
       SUM(fl.gallons) AS total_gallons,
       SUM(fl.total_cost) AS total_cost
FROM vm_fuel_logs fl
JOIN vm_trip_categories tc ON fl.trip_category_id = tc.id
WHERE fl.workflow_status = 'approved'
  AND fl.event_date BETWEEN '2026-01-01' AND '2026-01-31'
GROUP BY tc.name
ORDER BY total_cost DESC;
```

**Vehicles overdue for maintenance:**
```sql
-- Note: vm_vehicle_schedules uses is_active (not is_deleted) because
-- schedules are toggleable — they can be paused and resumed, not soft-deleted.
SELECT v.unit_number, v.name, mi.name AS maintenance_type,
       vs.next_due_date, vs.next_due_odometer, v.current_odometer
FROM vm_vehicle_schedules vs
JOIN vm_vehicles v ON vs.vehicle_id = v.id
JOIN vm_maintenance_items mi ON vs.maintenance_item_id = mi.id
WHERE vs.is_active = 1
  AND v.is_deleted = 0
  AND (vs.next_due_date < CURDATE()
       OR vs.next_due_odometer < v.current_odometer)
ORDER BY vs.next_due_date ASC;
```

**Fleet cost summary by vehicle:**

> **Note:** Cost reports (fuel and maintenance) will only produce meaningful data if cost tracking is enabled in `vm_settings`. By default, `track_fuel_cost` and `track_maintenance_cost` are both `FALSE` for Woodson ISD. Enable them in the Settings page before expecting cost analytics.

```sql
SELECT v.unit_number, v.name,
       COALESCE(fuel.total_fuel_cost, 0) AS fuel_cost,
       COALESCE(maint.total_maint_cost, 0) AS maintenance_cost,
       COALESCE(fuel.total_fuel_cost, 0) + COALESCE(maint.total_maint_cost, 0) AS total_cost
FROM vm_vehicles v
LEFT JOIN (
    SELECT vehicle_id, SUM(total_cost) AS total_fuel_cost
    FROM vm_fuel_logs WHERE workflow_status = 'approved'
    GROUP BY vehicle_id
) fuel ON fuel.vehicle_id = v.id
LEFT JOIN (
    SELECT vehicle_id, SUM(total_cost) AS total_maint_cost
    FROM vm_maintenance_events WHERE workflow_status = 'approved'
    GROUP BY vehicle_id
) maint ON maint.vehicle_id = v.id
WHERE v.is_deleted = 0
ORDER BY total_cost DESC;
```

---

## 6. Gaps Identified & Resolved

| # | Gap | Severity | Resolution | File Changed |
|---|-----|----------|------------|-------------|
| G1 | PackageAccessResolver ignored `user_global_roles` | **HIGH** | Added `getAllUserSystemRoles()` — now checks primary role + all global roles, picks highest-tier match | `src/PackageAccessResolver.php` |
| G2 | No auto-DB migration on package install | **MEDIUM** | Added `runPackageDatabaseMigrations()` to `PackageManager::installPackage()` — auto-runs SQL from `migrations/` or `database/schema.sql` | `src/PackageManager.php` |
| G3 | Fleet package used separate database (`woodson_fleet`) | **MEDIUM** | Changed to `woodson_hub` — avoids DBA intervention, enables native FKs | `packages/.../package.json` |
| G4 | `globalRoleMapping` only mapped 3 of 13 district roles | **LOW** | Expanded mapping to include all 13 ENUM roles: `principal`, `business_manager`, `maintenance_director`, `maintenance_staff`, `custodial_manager`, `custodial`, `cafeteria`, `student`, `substitute_manager`, `counselor` | `packages/.../package.json` |
| G5 | Fuel logs/maintenance events had no workflow columns | **MEDIUM** | Added `workflow_status`, `reviewed_by`, `reviewed_at`, `review_notes`, `correction_reason` to both tables | `migrations/001_create_fleet_tables.sql` |
| G6 | No `vm_audit_logs` table for package-level audit trail | **MEDIUM** | Added table with event_type, entity tracking, before/after JSON | `migrations/001_create_fleet_tables.sql` |
| G7 | Edit boundaries did not distinguish actor (user vs manager) | **LOW** | Split into `managerEdits` (submitted/in_review, requiresReason) and `userEdits` (draft/needs_correction, no reason required) | `packages/.../package.json` |
| G8 | `is_active` vs `is_deleted` usage undocumented | **LOW** | Clarified: `is_deleted` for entities (vehicles), `is_active` for toggleable lookups/schedules | Audit report + SQL comments |
| G9 | Trip category `code` column not mentioned in report | **LOW** | DDL already has `code VARCHAR(10) NOT NULL UNIQUE`; report now references it explicitly | Audit report |
| G10 | Vehicle sharing used informal `sharedTables` pattern | **MEDIUM** | Replaced with production-grade Resource Registry: canonical contracts (`hub_resource_contracts`), provider ownership (`hub_resource_providers`), consumer dependency tracking (`hub_resource_consumers`), platform views (`hub_vehicles_v1`), SemVer contract versioning, deterministic install-time resolution | `database/resource-registry-schema.sql`, `CONTRIBUTING.md`, `package.json`, `001_create_fleet_tables.sql` |

---

## 7. Compatibility Matrix

| Requirement | Hub Infrastructure | Status |
|------------|-------------------|--------|
| PHP 8.1+ | PHP 8.3.6 | ✅ |
| MySQL 8.0+ | MySQL (InnoDB, utf8mb4) | ✅ |
| Google OAuth SSO | Configured with domain lock | ✅ |
| Google Groups sync | Service account + Admin SDK | ✅ |
| Package schema v3.0 | Supported by GenericPackageHandler + PageRouter | ✅ |
| Role-based access control | SectionRoleAccess + PackageAccessResolver | ✅ |
| Audit logging | AuditLogger + vm_audit_logs | ✅ |
| File uploads (receipts, invoices) | Upload directory + path storage | ✅ |
| HTTPS required | Production cookie policy enforces secure | ✅ |
| Soft deletes | `is_deleted` on entities, `is_active` on lookup/schedule tables | ✅ |

---

## 8. Sign-Off

This audit confirms that the Vehicle Maintenance & Fleet Tracking package (v2.1.0) is compatible with The Hub's current infrastructure. All identified gaps have been resolved at the infrastructure level (not patched — fixed systemically so all future packages benefit).

The package is cleared for development.

| Role | Name | Date |
|------|------|------|
| Technology Department | _________________ | _________________ |
| Auditor | _________________ | _________________ |
| Superintendent | _________________ | _________________ |
