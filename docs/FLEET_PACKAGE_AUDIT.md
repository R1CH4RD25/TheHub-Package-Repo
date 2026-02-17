# Fleet Package Compatibility Audit Report

**Prepared for:** Woodson ISD Auditor  
**Date:** February 17, 2026  
**System:** The Hub v3.0 — Package Platform  
**Package:** Vehicle Maintenance & Fleet Tracking v2.1.0  
**Prepared by:** Technology Department  

---

## Executive Summary

This document verifies the compatibility of the Vehicle Maintenance & Fleet Tracking package with The Hub's infrastructure before development begins. Three systemic gaps were identified during the audit and have been resolved. The package is now cleared for build.

| Area | Status | Notes |
|------|--------|-------|
| Permission Model | ✅ PASS | Dual-path resolved (Google Groups + direct roles) |
| Database Architecture | ✅ PASS | Hub-native tables with `vm_` prefix |
| Auto-Migration | ✅ PASS | Tables created automatically on package install |
| Role Mapping | ✅ PASS | All 10 district roles mapped to package roles |
| Layer 2 Workflow | ✅ PASS | Approval states on fuel logs + maintenance events |
| Audit Trail | ✅ PASS | Package-level + system-level audit logging |
| Admin Dashboard | ✅ PASS | Section toggle matrix, role management, Google Sync UI |
| Reporting | ✅ PASS | SQL queries against `vm_*` tables via standard tools |

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

These map to district system roles via `globalRoleMapping`:

| District System Role | Fleet Package Role | Access Level |
|---------------------|-------------------|--------------|
| `super_admin` | `admin` | Full control (auto-highest) |
| `admin` | `manager` | Approve/reject, configure |
| `principal` | `manager` | Approve/reject, configure |
| `business_manager` | `manager` | Approve/reject, configure |
| `maintenance_director` | `manager` | Approve/reject, configure |
| `maintenance_staff` | `user` | View fleet, submit logs |
| `custodial_manager` | `user` | View fleet, submit logs |
| `custodial` | `user` | View fleet, submit logs |
| `staff` | `user` | View fleet, submit logs |
| *(unmapped roles)* | `user` | Falls back to `defaultRole` |

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
| `vm_settings` | Package configuration (single row) | CHAR(26) ULID | — |
| `vm_audit_logs` | Package audit trail | BIGINT AUTO | → `users.id` |

### 2.3 Data Integrity

- All tables use `InnoDB` engine with `utf8mb4` character set
- Foreign keys enforce referential integrity (`ON DELETE RESTRICT` for operational data, `ON DELETE SET NULL` for optional references, `ON DELETE CASCADE` for template items)
- Soft deletes via `is_deleted` flag on `vm_vehicles` — **no hard deletes**
- Trip category codes (11, 23, 34, 36, 41) are treated as **constants** — seeded during migration, never repurposed

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
                     corrected → approved
                         ↓
                     rejected
```

| State | Who Can Transition | Description |
|-------|-------------------|-------------|
| `draft` | User | Initial entry, not yet submitted |
| `submitted` | User | Submitted for manager review |
| `in_review` | Manager/Admin | Under active review |
| `corrected` | Manager/Admin | Sent back for correction with notes |
| `approved` | Manager/Admin | Accepted — immutable |
| `rejected` | Manager/Admin | Denied — requires new submission |

### 4.2 Edit Boundaries

Once submitted, records enforce edit boundaries:

| Field Category | Editable? | States Allowed |
|---------------|-----------|----------------|
| `fuel_gallons`, `odometer_reading`, `fuel_cost`, `trip_category_id` | ✅ With reason | `in_review`, `corrected` |
| `id`, `created_at`, `created_by_user_id`, `vehicle_id`, `fuel_date` | ❌ Never | — |

All edits to submitted records **require a correction reason** (`correction_reason` column).

### 4.3 Audit Events

Every workflow transition is logged to `vm_audit_logs`:

| Event | Trigger |
|-------|---------|
| `FUEL_LOG_SUBMITTED` | User submits fuel log |
| `FUEL_LOG_REVIEW_STARTED` | Manager begins review |
| `FUEL_LOG_CORRECTED` | Manager sends back for correction |
| `FUEL_LOG_APPROVED` | Manager approves |
| `FUEL_LOG_REJECTED` | Manager rejects |
| `MAINTENANCE_EVENT_SUBMITTED` | User submits maintenance event |
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
SELECT v.unit_number, v.name, mi.name AS maintenance_type,
       vs.next_due_date, vs.next_due_odometer, v.current_odometer
FROM vm_vehicle_schedules vs
JOIN vm_vehicles v ON vs.vehicle_id = v.id
JOIN vm_maintenance_items mi ON vs.maintenance_item_id = mi.id
WHERE vs.is_active = 1
  AND (vs.next_due_date < CURDATE() 
       OR vs.next_due_odometer < v.current_odometer)
ORDER BY vs.next_due_date ASC;
```

**Fleet cost summary by vehicle:**
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
| G4 | `globalRoleMapping` only mapped 3 of 10+ district roles | **LOW** | Expanded mapping to include `principal`, `business_manager`, `maintenance_director`, `maintenance_staff`, `custodial_manager`, `custodial` | `packages/.../package.json` |
| G5 | Fuel logs/maintenance events had no workflow columns | **MEDIUM** | Added `workflow_status`, `reviewed_by`, `reviewed_at`, `review_notes`, `correction_reason` to both tables | `migrations/001_create_fleet_tables.sql` |
| G6 | No `vm_audit_logs` table for package-level audit trail | **MEDIUM** | Added table with event_type, entity tracking, before/after JSON | `migrations/001_create_fleet_tables.sql` |

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
| Soft deletes | `is_deleted` flags, no hard deletes | ✅ |

---

## 8. Sign-Off

This audit confirms that the Vehicle Maintenance & Fleet Tracking package (v2.1.0) is compatible with The Hub's current infrastructure. All identified gaps have been resolved at the infrastructure level (not patched — fixed systemically so all future packages benefit).

The package is cleared for development.

| Role | Name | Date |
|------|------|------|
| Technology Department | _________________ | _________________ |
| Auditor | _________________ | _________________ |
| Superintendent | _________________ | _________________ |
