# Changelog

All notable changes to the Vehicle Maintenance & Fleet Tracking package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - 2026-02-11

### Added
- **Layer 2 Compliance (Audit-Grade Manager Oversight)**
  - `entities` block - Links workflows to database tables (fuel_log, maintenance_event)
  - `workflow_states` block - Formal state machines (SUBMITTED → IN_REVIEW → CORRECTED → APPROVED/REJECTED)
  - `manager_actions` block - Field-level edit boundaries with editable/immutable field definitions
  - `audit_events` block - Required logging taxonomy with required_fields for each event type
  - `vm_audit_logs` table for package-specific audit trail
  - `layer2-compliant` capability flag

- **Workflow States:**
  - Fuel Logs: SUBMITTED → IN_REVIEW → CORRECTED → APPROVED/REJECTED
  - Maintenance Events: SUBMITTED → IN_REVIEW → CORRECTED → APPROVED/REJECTED

- **Manager Edit Controls:**
  - Editable fields: fuel_gallons, odometer_reading, fuel_cost, trip_category_id (fuel logs)
  - Editable fields: odometer_reading, maintenance_cost, notes (maintenance events)
  - Immutable fields: id, created_at, created_by_user_id, vehicle_id, fuel_date, maintenance_date
  - Reason requirements: All numeric/financial edits require correction_reason

- **Audit Events (10 types):**
  - FUEL_LOG_SUBMITTED, FUEL_LOG_REVIEWED, FUEL_LOG_CORRECTED, FUEL_LOG_APPROVED, FUEL_LOG_REJECTED
  - MAINTENANCE_EVENT_SUBMITTED, MAINTENANCE_EVENT_REVIEWED, MAINTENANCE_EVENT_CORRECTED, MAINTENANCE_EVENT_APPROVED, MAINTENANCE_EVENT_REJECTED
  - All events include required_fields enforcement (manager_id, before/after values, correction reasons)

### Changed
- **Fixed access model** - Hub cards now use package-specific roles (vm_user, vm_manager, vm_admin) instead of generic "user"
- **Consolidated permissions** - Single `permissions` block with `workflow_actions` array (removed duplicate capabilities_by_role)
- **Upgraded roles** - Added `maps_to_global_role` field for platform role mapping
- **Package version** - 2.0.0 → 2.1.0
- **Database** - Added `vm_audit_logs` table (12 tables total, up from 11)

### Migration from 2.0.0
- v2.0.0 had UI separation (Hub cards + Management sections) but lacked formal enforcement blocks
- v2.1.0 adds Layer 2 compliance making "managers can correct submissions with audit-grade traceability" provable and validator-enforceable
- Declared controls (package JSON) now match Enforced controls (PackageValidator)
- Installation requires PackageValidator Layer 2 compliance checks to pass

### Validation
- PackageValidator enforces Layer 2 compliance at install time
- Packages with hub_cards must include entities, workflow_states, manager_actions, audit_events blocks
- `use_global_log: true` is mandatory (CRITICAL severity if false)
- Workflow states validated for orphaned transitions
- Manager actions validated for editable_fields and immutable_fields completeness

## [2.0.0] - 2026-02-08

### Added
- **Hub/Management Separation Architecture**
- Complete redesign with clear separation between user-facing Hub cards and Management sections
- 3 Hub cards for user access (fleet roster, fuel tracking, maintenance tracking)
- 4 Management sections with subsections (vehicles, fuel management, maintenance management, configuration)
- Improved role structure (vm_user, vm_manager, vm_admin)

### Changed
- Simplified role model from 5 roles to 3 roles
- Updated access patterns to align with Hub/Management paradigm
- Namespace changed to `vm` for consistency
- Package ID updated to `com.woodson.vehicle-maintenance`

## [1.0.0] - 2026-01-14

### Added
- **Initial release of Vehicle Maintenance & Fleet Tracking package**
- **Database Entities (11 tables):**
  - `vm_vehicles` - Fleet inventory with unit numbers, VIN, license plate, year/make/model
  - `vm_trip_categories` - Trip classification codes (11, 23, 34, 36, 41)
  - `vm_fuel_logs` - Fuel and trip tracking with purchase support
  - `vm_maintenance_items` - Master list of maintenance tasks
  - `vm_maintenance_events` - Completed maintenance logs
  - `vm_maintenance_templates` - Reusable maintenance plans
  - `vm_template_items` - Items within maintenance templates
  - `vm_vehicle_schedules` - Per-vehicle maintenance schedules
  - `vm_departments` - Department assignments (optional)
  - `vm_campuses` - Campus assignments (optional)
  - `vm_settings` - Package-wide configuration

- **Hub Cards (3):**
  - Fleet Management - View fleet roster
  - Fuel & Trip Tracking - Submit fuel logs, view own trips
  - Maintenance Tracking - Submit maintenance events, view own logs

- **Management Sections (5):**
  - Fleet Configuration - Manage vehicles, departments, campuses
  - Fuel Analytics - District-wide fuel consumption and cost tracking
  - Maintenance Management - Configure templates, items, schedules
  - Trip Categories - Manage trip classification codes
  - Package Settings - Configure package-wide settings

- **Roles (5):**
  - `hub_user` - View fleet, submit fuel/maintenance logs
  - `management_crew` - Log maintenance for all vehicles
  - `management_fleet_manager` - Manage assigned vehicles
  - `management_director` - District-wide control and configuration
  - `management_admin` - Full package administration

- **Features:**
  - Template-based maintenance scheduling (mileage + time intervals)
  - Automatic next-due calculations for maintenance schedules
  - Fuel purchase tracking with vendor, location, cost, receipt uploads
  - Multiple fuel types (Unleaded, Diesel, Propane, Other)
  - Out-of-service vehicle status with reason logging
  - Department and campus organization (optional, configurable)
  - Hub/Management dual-access architecture
  - Role-based access control with scoped permissions
  - File upload validation (receipts, invoices, photos)
  - Automatic odometer updates from fuel/maintenance logs

- **Validation:**
  - Year: 1900-2100
  - Odometer: 0-999,999 miles
  - Gallons: 0.01-9,999.99
  - Costs: 0.01-999,999.99
  - Price per gallon: 0.01-99.99
  - File uploads: PDF/JPG/PNG with size limits

- **Seed Data:**
  - 5 default trip categories (11, 23, 34, 36, 41)
  - 8 common maintenance items (Oil Change, Tire Rotation, etc.)
  - Standard Bus Template with 5 pre-configured maintenance items
  - Default package settings (driver logging enabled, 30-day lead time)

### Security
- Prepared statements for all database queries
- ULID-based primary keys for all entities
- Foreign key constraints with RESTRICT/CASCADE policies
- Input validation with type checking and range limits
- File upload validation with mime type enforcement
- XSS protection via output escaping
- Role-based access control for all modules

## [Unreleased]

### Planned for 1.1
- Excel export for fuel and maintenance logs
- Email notifications for upcoming maintenance
- Dashboard charts (fuel consumption trends, cost analytics)
- Bulk vehicle import via CSV
- QR code scanning for quick vehicle lookup

### Planned for 1.2
- Mobile-responsive fuel logging interface
- GPS integration for automatic odometer updates
- Fuel efficiency calculations (MPG tracking)
- Scheduled report generation
- Parts inventory integration

### Planned for 2.0
- Work order management system
- Parts inventory tracking
- Vendor management with purchase orders
- Advanced analytics and forecasting
- Third-party fleet management integrations

---

## Version History

### 1.0.0 (2026-01-14)
- Initial release
- Full Hub/Management dual-access architecture
- 11 database tables with comprehensive relationships
- 3 Hub cards, 5 Management sections
- Template-based maintenance scheduling
- Fuel purchase tracking with receipts
- Role-based permissions (5 levels)

---

## Compatibility

| Package Version | Hub Version | PHP Version | MySQL Version | Tested |
|----------------|-------------|-------------|---------------|--------|
| 1.0.0          | >=1.0.0 <2.0.0 | >=8.0    | >=5.7         | 1.3.0  |

---

## Migration Notes

### Upgrading to 1.0.0
This is the initial release. No migrations required.

### Database Schema Changes
- N/A (initial release)

### Breaking Changes
- N/A (initial release)

---

## Contributors

- Woodson ISD Technology Department
- The Hub Platform Team
- Richard Woodson - Lead Developer

---

For detailed information about changes, see the [commit history](https://github.com/R1CH4RD25/TheHub/commits/laravel-migration/packages/local/operations-fleet-maintenance).
