# Vehicle Maintenance & Fleet Tracking Package

**Version:** 1.0.0  
**Author:** Woodson ISD Technology Department  
**License:** Proprietary  
**Category:** Operations

## Overview

The Vehicle Maintenance & Fleet Tracking package provides comprehensive district-wide vehicle management, fuel tracking with trip categorization, maintenance scheduling with template-based plans, and role-based access control for drivers, maintenance crews, fleet managers, and directors.

## Features

### 🚗 Fleet Management
- Full vehicle inventory with VIN, license plate, year, make, model
- Unit number tracking for district identification
- Department and campus assignment (optional)
- Out-of-service status tracking with reason logging
- Current odometer tracking for mileage-based maintenance

### ⛽ Fuel & Trip Tracking
- Fuel logging with trip category classification
- Support for district tanks and external fuel purchases
- Vendor and purchase location tracking
- Cost tracking (total cost, price per gallon)
- Receipt upload for purchase documentation
- Multiple fuel types: Unleaded, Diesel, Propane, Other
- Driver self-service logging (optional, controlled by settings)

### 🔧 Maintenance Management
- Template-based maintenance plans (e.g., Bus Template, Truck Template)
- Mileage-based and time-based maintenance scheduling
- Automatic next-due calculations
- Maintenance event logging with vendor, parts, labor costs
- Invoice and photo uploads for documentation
- Assignment-based access control for crew and fleet managers

### 📊 Dashboard & Analytics
- District-wide fleet overview
- Upcoming maintenance due notifications
- Fuel consumption analytics
- Cost tracking and reporting
- Out-of-service vehicle monitoring

### 🔐 Role-Based Access Control
- **Driver (vm_driver):** Submit fuel/trip logs when enabled
- **Maintenance Crew (vm_crew):** Log fuel and maintenance events
- **Fleet Manager (vm_fm):** Manage assigned vehicles and schedules
- **Maintenance Director (vm_md):** District-wide control and configuration
- **Admin (vm_admin):** Full package administration

## Requirements

### Hub Core
- Hub Version: >=1.0.0 <2.0.0
- Tested up to: 1.3.0

### Server Environment
- PHP: 8.0 or higher
- MySQL: 5.7 or higher
- Disk Space: 500MB (includes uploads for receipts, invoices, photos)

### Dependencies
- Core Hub authentication system
- ULID library for unique identifiers
- File upload system with validation
- Notification system (email)

## Installation

### 1. Upload Package
- Navigate to **Admin → Packages** in the Hub
- Click **Upload Package**
- Select the `vehicle-maintenance` package ZIP file
- System will validate manifest structure and compatibility

### 2. Database Migration
The package will automatically create the following tables:
- `vm_vehicle` - Vehicle inventory
- `vm_department` - Department assignments
- `vm_campus` - Campus assignments
- `vm_trip_category` - Trip classification codes
- `vm_fuel_log` - Fuel and trip entries
- `vm_maintenance_item` - Master list of maintenance tasks
- `vm_maintenance_template` - Template-based maintenance plans
- `vm_template_item` - Items within each template
- `vm_vehicle_schedule_item` - Per-vehicle maintenance schedules
- `vm_maintenance_event` - Completed maintenance logs
- `vm_vehicle_assignment` - Crew/FM vehicle assignments
- `vm_settings` - Package-wide configuration

### 3. Initial Configuration
After installation:
1. Navigate to **Vehicle Maintenance Settings** (vm_md or vm_admin role required)
2. Configure:
   - Allow driver logging (enable/disable driver fuel entries)
   - Enable departments (optional organizational layer)
   - Enable campuses (optional organizational layer)
   - Maintenance lead time (days before due to notify)
   - Maintenance lead distance (miles before due to notify)

### 4. Setup Trip Categories
Navigate to **Trip Categories** and create district trip codes:
- Example: `11` - Extracurricular (athletics, UIL, field trips)
- Example: `23` - Student Transportation (routes, special ed)
- Example: `34` - District Business (admin, meetings)
- Example: `36` - Training/Professional Development
- Example: `41` - Maintenance/Operations

### 5. Create Maintenance Templates
Navigate to **Maintenance Templates** and create reusable plans:
- Example: Bus Template
  - Oil Change (every 5,000 miles or 90 days)
  - Tire Rotation (every 7,500 miles or 6 months)
  - Annual Inspection (every 365 days)
  - Air Filter (every 15,000 miles or 12 months)

### 6. Add Vehicles
Navigate to **Vehicles → Add Vehicle** and populate fleet inventory:
- Enter vehicle name, unit number, VIN, license plate
- Assign year, make, model
- Optionally assign to department/campus
- Apply maintenance template to auto-generate schedule

### 7. Assign Roles
Navigate to **Admin → Users** and assign package roles:
- Drivers: `vm_driver` (if driver logging enabled)
- Maintenance crew: `vm_crew`
- Fleet managers: `vm_fm`
- Maintenance director: `vm_md`
- Package administrators: `vm_admin`

## Usage

### For Drivers (vm_driver)
1. Navigate to **Fuel & Trip Log**
2. Select your vehicle from dropdown
3. Enter date, odometer, gallons, fuel type
4. Select trip category (dropdown)
5. If purchasing fuel, check "Purchasing Fuel?" and enter vendor, location, cost, upload receipt
6. Submit log entry

### For Maintenance Crews (vm_crew)
1. **Log Fuel:** Same as drivers, but can log for any vehicle
2. **Log Maintenance:**
   - Navigate to **Maintenance Logs → Add Maintenance**
   - Select vehicle and maintenance item
   - Enter date, odometer, vendor, costs
   - Upload invoice and photos (optional)
   - Submit maintenance event
   - System will auto-update vehicle schedule next-due dates

### For Fleet Managers (vm_fm)
1. **Manage Assigned Vehicles:**
   - View vehicles table (filtered by assignment)
   - Edit vehicle details, update out-of-service status
   - View dashboard with upcoming maintenance for assigned fleet
2. **Review Logs:**
   - View fuel logs and maintenance logs for assigned vehicles
   - Filter by date range, vehicle, trip category

### For Maintenance Directors (vm_md)
1. **District-Wide Control:**
   - View all vehicles, logs, schedules
   - Create/edit trip categories
   - Create/edit maintenance templates
   - Configure package settings
2. **Assign Fleet Managers:**
   - Navigate to **Vehicle Assignments**
   - Assign crews and fleet managers to specific vehicles

### For Administrators (vm_admin)
- Full access to all features
- Assign Maintenance Director role
- Package-level configuration

## Database Schema

### Entities Overview
| Entity | Primary Purpose | Key Fields |
|--------|----------------|------------|
| `vm_vehicle` | Fleet inventory | unit_number, name, vin, license_plate, year, make, model |
| `vm_fuel_log` | Fuel & trip tracking | vehicle_id, event_date, odometer, gallons, trip_category_id, is_purchase |
| `vm_maintenance_event` | Maintenance logs | vehicle_id, maintenance_item_id, event_date, odometer, cost_total |
| `vm_maintenance_template` | Reusable maintenance plans | name, description |
| `vm_template_item` | Items in templates | template_id, maintenance_item_id, mileage_interval, time_interval_days |
| `vm_vehicle_schedule_item` | Per-vehicle schedules | vehicle_id, maintenance_item_id, last_service_date, next_due_date |
| `vm_trip_category` | Trip classification codes | code, name, description |

### Foreign Key Relationships
- `vm_fuel_log.vehicle_id` → `vm_vehicle.id` (RESTRICT)
- `vm_fuel_log.trip_category_id` → `vm_trip_category.id` (RESTRICT)
- `vm_maintenance_event.vehicle_id` → `vm_vehicle.id` (RESTRICT)
- `vm_maintenance_event.maintenance_item_id` → `vm_maintenance_item.id` (RESTRICT)
- `vm_vehicle_schedule_item.vehicle_id` → `vm_vehicle.id` (RESTRICT)
- `vm_template_item.template_id` → `vm_maintenance_template.id` (CASCADE)

## Permissions Matrix

| Role | Dashboard | Add Vehicle | Fuel Log | Maintenance Log | Templates | Settings | Scope |
|------|-----------|-------------|----------|-----------------|-----------|----------|-------|
| vm_driver | ❌ | ❌ | ✅ (own) | ❌ | ❌ | ❌ | Own entries only |
| vm_crew | ✅ | ❌ | ✅ | ✅ | ❌ | ❌ | All vehicles |
| vm_fm | ✅ | ✅ (scope) | ✅ | ✅ | Read-only | ❌ | Assigned vehicles |
| vm_md | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | District-wide |
| vm_admin | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | District-wide |

## Module Reference

### Dashboard (`vm-dashboard`)
- **Route:** `/pkg/vm/dashboard`
- **Icon:** fa-solid fa-gauge-high
- **Access:** vm_crew, vm_fm, vm_md, vm_admin
- **Features:**
  - Fleet overview statistics
  - Upcoming maintenance notifications
  - Out-of-service vehicles
  - Recent fuel and maintenance logs

### Vehicles Table (`vehicles-table`)
- **Route:** `/pkg/vm/vehicles`
- **Icon:** fa-solid fa-car-side
- **Access:** vm_crew, vm_fm, vm_md, vm_admin
- **Features:**
  - Sortable/filterable vehicle list
  - Unit number, name, license plate, department, campus
  - Out-of-service badge
  - Quick edit actions

### Vehicle Form (`vehicle-form`)
- **Route:** `/pkg/vm/vehicle-form`
- **Icon:** fa-solid fa-plus
- **Access:** vm_fm, vm_md, vm_admin
- **Features:**
  - Add/edit vehicle details
  - Validation: year (1900-2100), VIN (32 chars max)
  - Out-of-service status toggle with reason

### Fuel & Trip Log Form (`fuel-log-form`)
- **Route:** `/pkg/vm/fuel-log`
- **Icon:** fa-solid fa-gas-pump
- **Access:** vm_driver, vm_crew, vm_fm, vm_md, vm_admin
- **Features:**
  - Vehicle selector
  - Fuel type dropdown (Unleaded, Diesel, Propane, Other)
  - Trip category dropdown
  - Conditional purchase fields (vendor, location, cost, receipt)
  - Validation: odometer (0-999999), gallons (0.01-9999), costs (0.01-999999.99)

### Fuel Logs Table (`fuel-log-table`)
- **Route:** `/pkg/vm/fuel-logs`
- **Icon:** fa-solid fa-table
- **Access:** vm_crew, vm_fm, vm_md, vm_admin
- **Features:**
  - Date range filter
  - Vehicle/trip category filters
  - Purchase badge indicator
  - Export to Excel (future)

### Maintenance Log Form (`maintenance-event-form`)
- **Route:** `/pkg/vm/maintenance-log`
- **Icon:** fa-solid fa-wrench
- **Access:** vm_crew, vm_fm, vm_md, vm_admin
- **Features:**
  - Vehicle and maintenance item selectors
  - Cost breakdown (parts, labor, total)
  - Invoice upload (PDF, JPG, PNG up to 5MB)
  - Photo upload (JPG, PNG up to 10MB)
  - Auto-updates vehicle schedule next-due dates

### Maintenance Records Table (`maintenance-events-table`)
- **Route:** `/pkg/vm/maintenance-logs`
- **Icon:** fa-solid fa-screwdriver-wrench
- **Access:** vm_crew, vm_fm, vm_md, vm_admin
- **Features:**
  - Date range filter
  - Vehicle/item filters
  - Cost sorting
  - View invoice/photos

### Trip Categories (`trip-categories`)
- **Route:** `/pkg/vm/trip-categories`
- **Icon:** fa-solid fa-tags
- **Access:** vm_md, vm_admin
- **Features:**
  - Manage district trip codes
  - Code, name, description fields
  - Active/inactive toggle

### Maintenance Templates (`maintenance-templates`)
- **Route:** `/pkg/vm/maintenance-templates`
- **Icon:** fa-solid fa-layer-group
- **Access:** vm_md, vm_admin
- **Features:**
  - Create reusable maintenance plans
  - Add multiple maintenance items per template
  - Define mileage and time intervals
  - Apply templates to vehicles

### Settings Form (`vm-settings-form`)
- **Route:** `/pkg/vm/settings`
- **Icon:** fa-solid fa-gear
- **Access:** vm_md, vm_admin
- **Features:**
  - Allow driver logging toggle
  - Enable departments/campuses toggles
  - Maintenance lead time (days: 1-365)
  - Maintenance lead distance (miles: 1-10000)

## Security & Validation

### Input Validation
- **Year:** 1900-2100
- **Odometer:** 0-999,999 miles
- **Gallons:** 0.01-9,999.00
- **Costs:** 0.01-999,999.99
- **Price per gallon:** 0.01-99.99

### File Upload Validation
- **Receipt/Invoice:** PDF, JPG, PNG up to 5MB
- **Photos:** JPG, PNG up to 10MB
- Mime type enforcement
- Antivirus scanning (future)

### Rate Limiting
- **Fuel Log Form:** 20/user, 10/min, 60/hour
- **Maintenance Event Form:** 50/user, 20/min, 200/hour

### SQL Injection Prevention
- Prepared statements for all queries
- ULID validation (26 chars, alphanumeric)
- Foreign key constraints enforce referential integrity

### XSS Protection
- Output escaping via `htmlspecialchars()`
- CSP headers in Hub core
- No inline JavaScript in forms

## Troubleshooting

### "Vehicle not found" when submitting fuel log
- Ensure vehicle exists in `vm_vehicle` table
- Check `is_deleted = FALSE`
- Verify user has access to selected vehicle (scope check for vm_fm)

### "Trip category required" error
- Trip categories must exist in `vm_trip_category` table
- Check `is_active = TRUE`
- Directors must create categories before drivers can log trips

### Maintenance schedule not auto-updating
- Verify `vm_vehicle_schedule_item` exists for vehicle+item combination
- Check `is_active = TRUE` on schedule item
- Ensure maintenance event date > last_service_date
- Review application logs for calculation errors

### Dashboard not loading
- Check user role assignment (`vm_crew` or higher)
- Verify modules table has `vm-dashboard` entry with correct access array
- Clear browser cache and session

### File uploads failing
- Check `uploads/` directory permissions (775)
- Verify max file size in php.ini (`upload_max_filesize`, `post_max_size`)
- Review `logs/php-errors.log` for specific error

## Support

- **Repository:** https://github.com/R1CH4RD25/TheHub
- **Issues:** https://github.com/R1CH4RD25/TheHub/issues
- **Documentation:** https://github.com/R1CH4RD25/TheHub/tree/main/packages/local/vehicle-maintenance
- **Contact:** tech@woodsonisd.net

## Roadmap

### Version 1.1 (Planned)
- Excel export for fuel and maintenance logs
- Email notifications for upcoming maintenance
- Dashboard charts (fuel consumption trends, cost analytics)
- Bulk vehicle import via CSV

### Version 1.2 (Planned)
- Mobile-responsive fuel logging
- GPS integration for automatic odometer updates
- Fuel efficiency calculations (MPG)
- Scheduled report generation

### Version 2.0 (Future)
- Work order management
- Parts inventory tracking
- Vendor management system
- Advanced analytics and forecasting

## License

Proprietary software developed for Woodson ISD. All rights reserved.

## Credits

Developed by the Woodson ISD Technology Department as part of The Hub modular platform initiative.
