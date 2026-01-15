# Changelog

All notable changes to the Vehicle Maintenance & Fleet Tracking package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-11-13

### Added
- Initial release of Vehicle Maintenance & Fleet Tracking package
- **Database Entities (12):**
  - `vm_vehicle` - Fleet inventory with unit numbers, VIN, license plate, year/make/model
  - `vm_department` - Department assignments for vehicles
  - `vm_campus` - Campus assignments for vehicles
  - `vm_trip_category` - Trip classification codes for fuel logging
  - `vm_fuel_log` - Fuel and trip tracking with purchase support
  - `vm_maintenance_item` - Master list of maintenance tasks
  - `vm_maintenance_template` - Template-based maintenance plans
  - `vm_template_item` - Items within maintenance templates
  - `vm_vehicle_schedule_item` - Per-vehicle maintenance schedules
  - `vm_maintenance_event` - Completed maintenance logs
  - `vm_vehicle_assignment` - Crew/FM vehicle assignments
  - `vm_settings` - Package-wide configuration
- **Modules (11):**
  - Dashboard with fleet overview and upcoming maintenance
  - Vehicles table (sortable, filterable with out-of-service indicators)
  - Vehicle form (add/edit with validation)
  - Fuel & Trip Log form (conditional purchase fields, receipt uploads)
  - Fuel Logs table (date range filters, purchase badges)
  - Maintenance Log form (invoice and photo uploads, cost tracking)
  - Maintenance Records table (filterable by vehicle, item, date)
  - Trip Categories table (admin-only configuration)
  - Maintenance Templates table (reusable maintenance plans)
  - Settings form (driver logging, departments, campuses, lead times)
- **Roles (5):**
  - `vm_driver` - Submit fuel/trip logs (when enabled)
  - `vm_crew` - Log fuel and maintenance events
  - `vm_fm` - Fleet Manager with assignment-based access
  - `vm_md` - Maintenance Director with district-wide control
  - `vm_admin` - Full package administration
- **Features:**
  - Template-based maintenance scheduling (mileage + time intervals)
  - Automatic next-due calculations for maintenance schedules
  - Fuel purchase tracking with vendor, location, cost, receipt uploads
  - Multiple fuel types (Unleaded, Diesel, Propane, Other)
  - Out-of-service vehicle status with reason logging
  - Department and campus organization (optional, configurable)
  - Driver self-service logging (optional, configurable)
  - Role-based access control with scoped permissions
  - File upload validation (receipts, invoices, photos)
  - Rate limiting for form submissions
- **Validation:**
  - Year: 1900-2100
  - Odometer: 0-999,999 miles
  - Gallons: 0.01-9,999.00
  - Costs: 0.01-999,999.99
  - Price per gallon: 0.01-99.99
  - File uploads: PDF/JPG/PNG, max 5-10MB
- **Security:**
  - Prepared statements for all database queries
  - Foreign key constraints for referential integrity
  - ULID validation (26 chars alphanumeric)
  - XSS protection via output escaping
  - Rate limiting per user/minute/hour

### Changed
- N/A (initial release)

### Deprecated
- N/A (initial release)

### Removed
- N/A (initial release)

### Fixed
- N/A (initial release)

### Security
- N/A (initial release)

---

## [Unreleased]

### Planned for 1.1.0
- Excel export for fuel and maintenance logs
- Email notifications for upcoming maintenance (X days/miles before due)
- Dashboard charts (fuel consumption trends, cost analytics)
- Bulk vehicle import via CSV

### Planned for 1.2.0
- Mobile-responsive fuel logging UI
- GPS integration for automatic odometer updates
- Fuel efficiency calculations (MPG per vehicle/trip category)
- Scheduled report generation (weekly/monthly summaries)

### Planned for 2.0.0
- Work order management system
- Parts inventory tracking
- Vendor management with contact/contract information
- Advanced analytics and forecasting

---

## Version History

- **1.0.0** - 2025-11-13 - Initial release with 12 entities, 11 modules, 5 roles

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

---

For detailed information about changes, see the [commit history](https://github.com/R1CH4RD25/TheHub/commits/main/packages/local/vehicle-maintenance).
