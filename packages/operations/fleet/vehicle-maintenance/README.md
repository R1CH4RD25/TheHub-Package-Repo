# Vehicle Maintenance & Fleet Tracking

> **Package ID:** `operations.vehicle-maintenance`  
> **Category:** Operations  
> **Schema:** v3.0.0  
> **Version:** 2.1.0  

District-wide fleet inventory, fuel/trip tracking, and maintenance scheduling with **Layer 2 manager oversight** workflows.

---

## Features

| Area | Capability |
|------|-----------|
| **Fleet Roster** | Central vehicle inventory with unit #, VIN, odometer, OOS status, department/campus assignment |
| **Fuel Logs** | Staff submit fuel logs per trip with odometer, gallons, trip category, vendor, cost, receipt |
| **Maintenance Logs** | Staff submit maintenance events with service type, vendor, parts/labor costs, invoices |
| **Layer 2 Workflow** | Submitted → In Review → Corrected / Approved / Rejected — managers review, correct, approve |
| **Maintenance Schedules** | Templates define interval-based service items; applied to vehicles for due-date tracking |
| **Trip Categories** | District-standard codes (11, 23, 34, 36, 41) for fuel log classification |
| **Dashboards** | KPI cards for fleet health, fuel stats, maintenance costs, pending reviews |

## Pages

| Page | Route | Who Sees It |
|------|-------|-------------|
| Fleet Overview | `/` | All users |
| Vehicle Detail | `/vehicle/{id}` | All users |
| Add Vehicle | `/vehicle/add` | Manager, Admin |
| Submit Fuel Log | `/fuel/add` | All users |
| Fuel Logs | `/fuel` | All users |
| Fuel Log Detail | `/fuel/{id}` | All users |
| Submit Maintenance | `/maintenance/add` | All users |
| Maintenance Logs | `/maintenance` | All users |
| Maintenance Schedules | `/schedules` | Manager, Admin |
| Trip Categories | `/config/trip-categories` | Admin |
| Maintenance Templates | `/config/templates` | Admin |

## Roles & Access

| Package Role | System Mapping | Permissions |
|-------------|----------------|-------------|
| **user** | `staff` | View fleet, submit fuel/maintenance logs, view own records |
| **manager** | `admin` | + Review/correct/approve, manage vehicles, schedules |
| **admin** | `super_admin` | + Configure trip categories, templates, settings |

### Layer 2 Workflow

```
submitted → in_review → corrected → approved
                     ↘ rejected
```

- **Edit boundaries** enforce which fields managers can correct (e.g., gallons, odometer — not created_by or vehicle_id)
- **Correction reason required** for all field changes
- **Full audit trail** on every state transition

## Database

| Table | Purpose |
|-------|---------|
| `vm_vehicles` | Fleet roster |
| `vm_departments` | Department list |
| `vm_campuses` | Campus list |
| `vm_trip_categories` | Trip purpose codes |
| `vm_fuel_logs` | Fuel/trip entries |
| `vm_maintenance_items` | Service type catalog |
| `vm_maintenance_templates` | Named groups of items with intervals |
| `vm_maintenance_template_items` | Item-to-template mapping with intervals |
| `vm_maintenance_events` | Service records |
| `vm_vehicle_schedules` | Per-vehicle schedule tracking |
| `vm_audit_logs` | Package-level audit trail |

**Connection:** `woodson_fleet` (separate database)

## Installation

1. Copy `package.json` into `packages/operations/fleet/vehicle-maintenance/`
2. Run `php cli/migrate-modules.php` to register the section
3. Insert into `section_packages` with `package_data` = contents of `package.json`
4. Assign section access in Admin → Sections
5. Create `woodson_fleet` database and run the schema in `database/schema.sql`

## File Structure

```
packages/operations/fleet/vehicle-maintenance/
├── package.json              # Schema v3.0.0 package definition
├── vehicle-maintenance_2.1.0.hubpkg  # Distributable copy
├── README.md
├── CHANGELOG.md
└── database/
    └── schema.sql            # Fleet database DDL
```

## Trip Category Codes

| Code | Name |
|------|------|
| 11 | Extracurricular Activities |
| 23 | Student Transportation |
| 34 | Business / Administrative |
| 36 | Professional Development |
| 41 | Maintenance / Repair |

> These codes are treated as **constants** — do not repurpose or reorder without admin sign-off.

---

*Built for The Hub — Woodson ISD*
