# Vehicle Maintenance & Fleet Tracking Package

**Version:** 2.1.0  
**Author:** Woodson ISD Technology Department  
**License:** Proprietary  
**Category:** Operations  
**Package ID:** `com.woodson.vehicle-maintenance`  
**Compliance:** Layer 2 Compliant (Audit-Grade Manager Oversight)

## Overview

The Vehicle Maintenance & Fleet Tracking package provides comprehensive district-wide vehicle management, fuel tracking with trip categorization, and maintenance scheduling. Designed for The Hub's 3-layer operational model, it separates user submissions (Hub Layer 1) from manager oversight (Management Layer 2) and platform configuration (Administrator Layer 3), ensuring audit-grade traceability for all manager corrections.

**v2.1.0 introduces Layer 2 compliance** with formal workflow state machines, field-level edit boundaries, and required audit event logging, making this package suitable for external audits and compliance reviews.

## Features

### 🚗 Fleet Management
- **Hub Access:** View-only fleet roster with vehicle details
- **Management Access:** Full CRUD for vehicles, departments, campuses
- Vehicle inventory: unit number, VIN, license plate, year/make/model
- Out-of-service status tracking with reason logging
- Department and campus assignment (optional)
- Current odometer tracking (auto-updated from logs)

### ⛽ Fuel & Trip Tracking
- **Hub Access:** Submit fuel logs, view own trip history
- **Management Access:** District-wide fuel analytics, configure trip categories
- Trip classification codes: 11 (Extracurricular), 23 (Student Transport), 34 (District Business), 36 (Training), 41 (Maintenance)
- Support for district tanks and external fuel purchases
- Vendor, location, cost tracking with receipt uploads
- Multiple fuel types: Unleaded, Diesel, Propane, Other
- Automatic odometer updates to vehicle records

### 🔧 Maintenance Tracking
- **Hub Access:** Submit maintenance events, view own service logs
- **Management Access:** Configure templates, schedules, district-wide analytics
- Template-based maintenance plans (Bus Template, Truck Template, etc.)
- Mileage-based and time-based scheduling intervals
- Automatic next-due calculations
- Maintenance event logging with vendor, parts/labor costs
- Invoice and photo uploads for documentation
- Service history tracking per vehicle

### 📊 Analytics & Reporting
- **Management Only:** District-wide dashboards
- Fuel consumption trends and cost analysis
- Upcoming maintenance due notifications
- Out-of-service vehicle monitoring
- Cost tracking by vehicle, department, campus
- Trip category analysis and reporting

### 🔐 Role-Based Access Control
- **vm_user (Hub User):** View fleet, submit fuel/maintenance logs, view own records
- **vm_manager (Fleet Manager):** Review, correct, and approve all submissions; manage fleet, templates, schedules
- **vm_admin (Fleet Administrator):** Full package administration, configure categories, departments, campuses, settings

### 🔒 Layer 2 Compliance (Audit-Grade Manager Oversight)

**What is Layer 2?** This package implements formal workflows, edit boundaries, and audit trails that make manager corrections provable and validator-enforceable. Every manager edit is logged with before/after values and correction reasons.

#### Workflow State Machines
Both fuel logs and maintenance events follow formal state transitions:
- **SUBMITTED** - User creates record (default state)
- **IN_REVIEW** - Manager begins review
- **CORRECTED** - Manager corrects errors in submission
- **APPROVED** - Manager approves (terminal state)
- **REJECTED** - Manager rejects (terminal state)

#### Manager Edit Boundaries
Managers can edit specific fields only in allowed states:

**Fuel Logs (Editable with Reason):**
- `fuel_gallons` - Corrects user entry errors
- `odometer_reading` - Fixes odometer mistakes
- `fuel_cost` - Corrects pricing errors
- `trip_category_id` - Recategorizes trips

**Fuel Logs (Immutable):**
- `id`, `created_at`, `created_by_user_id`, `vehicle_id`, `fuel_date`

**Maintenance Events (Editable with Reason):**
- `odometer_reading` - Fixes odometer mistakes
- `maintenance_cost` - Corrects cost errors
- `notes` - Adds clarification

**Maintenance Events (Immutable):**
- `id`, `created_at`, `created_by_user_id`, `vehicle_id`, `maintenance_date`

#### Audit Event Logging
All actions generate audit events with required fields:
- **FUEL_LOG_SUBMITTED** - Logs user_id, vehicle_id, fuel_gallons, odometer_reading, trip_category_id
- **FUEL_LOG_CORRECTED** - Logs manager_id, fuel_log_id, field_name, old_value, new_value, correction_reason
- **FUEL_LOG_APPROVED/REJECTED** - Logs manager_id, fuel_log_id, (rejection_reason if rejected)
- *Same pattern for MAINTENANCE_EVENT_* events

All audit events write to global `audit_logs` table (immutable, indefinite retention) and optional package-specific `vm_audit_logs` table.

## Requirements

### Hub Core
- Hub Version: >=1.0.0 <3.0.0
- Tested up to: 2.0.0
- PackageValidator with Layer 2 compliance enforcement

### Server Environment
- PHP: 8.0 or higher
- MySQL: 5.7 or higher (MariaDB 10.3+)
- Disk Space: 500MB (for file uploads)

### Dependencies
- Core Hub authentication system
- ULID library for unique identifiers
- File upload system with validation
- Hub/Management dual-access architecture

## Installation

### 1. Upload Package
- Navigate to **Management → Packages** in The Hub
- Click **Upload Package**
- Select the `com.woodson.vehicle-maintenance` package file (v2.1.0)
- System will validate manifest structure, compatibility, and **Layer 2 compliance**
- PackageValidator checks: entities, workflow_states, manager_actions, audit_events, use_global_log requirement

### 2. Database Migration
The package will automatically create 12 tables:
- `vm_vehicles` - Fleet inventory
- `vm_trip_categories` - Trip classification codes (11, 23, 34, 36, 41)
- `vm_fuel_logs` - Fuel and trip entries with workflow status
- `vm_maintenance_items` - Master list of maintenance types
- `vm_maintenance_events` - Completed maintenance logs with workflow status
- `vm_maintenance_templates` - Reusable maintenance plans
- `vm_template_items` - Items within each template
- `vm_vehicle_schedules` - Per-vehicle maintenance schedules
- `vm_departments` - Department assignments (optional)
- `vm_campuses` - Campus assignments (optional)
- `vm_settings` - Package-wide configuration
- `vm_audit_logs` - **NEW:** Package-specific audit trail (Layer 2 compliance)

### 3. Initial Configuration
After installation:
1. Navigate to **Management → Vehicle Maintenance → Package Settings**
2. Configure:
   - Allow driver logging (enable Hub users to submit fuel logs)
   - Enable departments/campuses (optional organizational layers)
   - Maintenance lead time (days before due to notify)
   - Maintenance lead distance (miles before due to notify)

### 4. Setup Trip Categories
Trip categories are pre-seeded with standard codes:
- **11** - Extracurricular (athletics, UIL, field trips)
- **23** - Student Transportation (routes, special education)
- **34** - District Business (admin meetings, professional development)
- **36** - Training & Professional Development
- **41** - Maintenance & Operations

Navigate to **Management → Trip Categories** to add/edit/deactivate codes.

### 5. Create Maintenance Templates
Navigate to **Management → Maintenance Templates**:
- Example: **Bus Template**
  - Oil Change (every 5,000 miles or 90 days)
  - Tire Rotation (every 7,500 miles or 180 days)
  - Air Filter (every 15,000 miles or 365 days)
  - Annual Inspection (every 365 days)
  - Brake Inspection (every 25,000 miles or 180 days)

### 6. Add Vehicles
Navigate to **Management → Fleet Configuration → Manage Vehicles**:
- Enter unit number (e.g., "BUS-01"), name, VIN, license plate
- Assign year, make, model
- Optionally assign to department/campus
- Apply maintenance template to auto-generate schedule

### 7. Assign Permissions
Navigate to **Management → Users** and assign package permissions:
- Hub users: Automatic access to view fleet and submit logs
- Maintenance crew: `management_crew` permission
- Fleet managers: `management_fleet_manager` permission
- Maintenance director: `management_director` permission
- Package administrators: `management_admin` permission

## Usage

### For Hub Users
1. **View Fleet:**
   - Navigate to **Hub → Vehicle Maintenance & Fleet → Fleet Management**
   - View all vehicles with unit numbers, make/model, status

2. **Log Fuel Trip:**
   - Navigate to **Hub → Fuel & Trip Tracking → Submit Fuel Log**
   - Select vehicle, enter date, odometer, gallons
   - Select trip category (11, 23, 34, 36, 41)
   - If purchasing fuel: check "Purchased Fuel?" and enter vendor, location, cost, upload receipt
   - Submit log

3. **Log Maintenance:**
   - Navigate to **Hub → Maintenance Tracking → Submit Maintenance Log**
   - Select vehicle and maintenance type (Oil Change, Tire Rotation, etc.)
   - Enter date, odometer, vendor
   - Enter costs (parts, labor)
   - Upload invoice and photos (optional)
   - Submit event

4. **View Own Logs:**
   - Navigate to **Fuel & Trip Tracking → My Fuel Logs**
   - Navigate to **Maintenance Tracking → My Maintenance Logs**
   - Filter by date, vehicle, trip category

### For Management Crew
- All Hub user capabilities PLUS:
- Log fuel/maintenance for ANY vehicle (not just own entries)
- View all fuel and maintenance logs district-wide

### For Management Fleet Managers
- All crew capabilities PLUS:
- Add/edit/delete assigned vehicles
- Update out-of-service status with reason
- View maintenance schedules for assigned fleet
- Analytics dashboard for assigned vehicles

### For Management Directors
- All fleet manager capabilities PLUS:
- Configure trip categories (add/edit/deactivate)
- Configure maintenance items (add/edit default intervals)
- Create/edit maintenance templates
- Manage departments and campuses
- Configure package settings (lead times, enable/disable features)
- View district-wide analytics and cost reports

### For Management Admins
- Full access to all features
- Assign permissions to other users
- Package-level configuration

## Database Schema

### Key Relationships
```
vm_vehicles (1) ──< (many) vm_fuel_logs
vm_vehicles (1) ──< (many) vm_maintenance_events
vm_vehicles (1) ──< (many) vm_vehicle_schedules

vm_trip_categories (1) ──< (many) vm_fuel_logs

vm_maintenance_items (1) ──< (many) vm_maintenance_events
vm_maintenance_items (1) ──< (many) vm_template_items
vm_maintenance_items (1) ──< (many) vm_vehicle_schedules

vm_maintenance_templates (1) ──< (many) vm_template_items

vm_departments (1) ──< (many) vm_vehicles (optional)
vm_campuses (1) ──< (many) vm_vehicles (optional)
```

### Foreign Key Constraints
- `vm_fuel_logs.vehicle_id` → `vm_vehicles.id` (RESTRICT)
- `vm_fuel_logs.trip_category_id` → `vm_trip_categories.id` (RESTRICT)
- `vm_maintenance_events.vehicle_id` → `vm_vehicles.id` (RESTRICT)
- `vm_maintenance_events.maintenance_item_id` → `vm_maintenance_items.id` (RESTRICT)
- `vm_vehicle_schedules.vehicle_id` → `vm_vehicles.id` (RESTRICT)
- `vm_template_items.template_id` → `vm_maintenance_templates.id` (CASCADE)

## Permissions Matrix

| Role | View Fleet | Submit Fuel | Submit Maint | Config Vehicles | Config Templates | Config Settings | Scope |
|------|-----------|-------------|--------------|-----------------|------------------|-----------------|-------|
| Hub User | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | Own entries |
| Mgt Crew | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | All vehicles |
| Mgt Fleet Mgr | ✅ | ✅ | ✅ | ✅ (assigned) | Read-only | ❌ | Assigned vehicles |
| Mgt Director | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | District-wide |
| Mgt Admin | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | District-wide |

## Security & Validation

### Input Validation
- **Year:** 1900-2100
- **Odometer:** 0-999,999 miles
- **Gallons:** 0.01-9,999.99
- **Costs:** 0.01-999,999.99
- **Price per gallon:** 0.01-99.99

### File Upload Validation
- **Fuel Receipts:** PDF, JPG, PNG up to 5MB
- **Maintenance Invoices:** PDF, JPG, PNG up to 5MB
- **Maintenance Photos:** JPG, PNG up to 10MB
- Mime type enforcement
- Secure file storage outside web root
- Unique ULID-based filenames

### SQL Injection Prevention
- Prepared statements for all database queries
- ULID validation (26 characters, alphanumeric)
- Foreign key constraints enforce referential integrity

### XSS Protection
- Output escaping via `htmlspecialchars()`
- CSP headers in Hub core
- No inline JavaScript in forms

## Troubleshooting

### "Vehicle not found" when submitting fuel log
- Ensure vehicle exists in `vm_vehicles` table
- Check `is_deleted = FALSE`
- Verify vehicle is not out of service (or check settings to allow logging for OOS vehicles)

### "Trip category required" error
- Trip categories must exist in `vm_trip_categories` table
- Check `is_active = TRUE`
- Directors must create/activate categories before users can log trips

### Maintenance schedule not auto-updating
- Verify `vm_vehicle_schedules` exists for vehicle+item combination
- Check `is_active = TRUE` on schedule item
- Ensure maintenance event date > last_service_date
- Review application logs for calculation errors

### File uploads failing
- Check upload directory permissions (775)
- Verify max file size in `php.ini` (`upload_max_filesize`, `post_max_size`)
- Review `logs/php-errors.log` for specific errors
- Ensure disk space available (500MB minimum)

## Support

- **Repository:** https://github.com/R1CH4RD25/TheHub
- **Package Repo:** https://github.com/R1CH4RD25/TheHub-Package-Repo
- **Issues:** https://github.com/R1CH4RD25/TheHub/issues
- **Contact:** tech@woodsonisd.net

## Roadmap

### Version 1.1 (Planned)
- Excel export for fuel and maintenance logs
- Email notifications for upcoming maintenance
- Dashboard charts (fuel consumption trends, cost analytics)
- Bulk vehicle import via CSV
- QR code scanning for quick vehicle lookup

### Version 1.2 (Planned)
- Mobile-responsive fuel logging interface
- GPS integration for automatic odometer updates
- Fuel efficiency calculations (MPG tracking)
- Scheduled report generation and email delivery
- Parts inventory integration

### Version 2.0 (Future)
- Work order management system
- Parts inventory tracking
- Vendor management with purchase orders
- Advanced analytics and forecasting
- Integration with third-party fleet management systems

## License

Proprietary software developed for Woodson ISD. All rights reserved.

This software is licensed for use within Woodson Independent School District only. Redistribution, modification, or use outside of Woodson ISD is prohibited without explicit written permission.

## Credits

Developed by the **Woodson ISD Technology Department** as part of The Hub modular platform initiative.

**Contributors:**
- Richard Woodson - Lead Developer
- The Hub Platform Team

---

**Last Updated:** January 14, 2026  
**Package Version:** 1.0.0  
**Hub Version Compatibility:** >=1.0.0 <2.0.0
