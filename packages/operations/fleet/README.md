# Fleet Management Packages

Vehicle fleet tracking, fuel logging, maintenance scheduling, and driver management tools.

## Packages

### 🚗 Vehicle Maintenance & Fleet Tracking v1.0.0
**Directory:** `vehicle-maintenance/`

Comprehensive district-wide vehicle maintenance, fueling, and trip category tracking with templates, notifications, and role-based control.

**Features:**
- Fleet inventory management (VIN, license plate, unit numbers, year/make/model)
- Fuel logging with trip categorization (district tank or purchases)
- Template-based maintenance scheduling (mileage + time intervals)
- Maintenance event tracking with invoice/photo uploads
- Department and campus organization (optional)
- Driver self-service logging (optional, configurable)
- Auto-calculated next-due maintenance dates
- Out-of-service vehicle status tracking
- Role-based access control (5 levels: driver, crew, fleet manager, maintenance director, admin)

**Database Entities:** 12 tables (vehicles, fuel logs, maintenance events, templates, schedules, trip categories, departments, campuses, settings)

**Modules:** 11 modules (dashboard, forms, tables with 5 role-based views)

**Tags:** `operations`, `fleet`, `vehicles`, `maintenance`, `fuel`, `school-operations`

**For:** Transportation departments, maintenance crews, fleet managers, maintenance directors

**Requirements:**
- Hub >=1.0.0 <2.0.0
- PHP >=8.0
- MySQL >=5.7
- Tested on Hub 1.3.0

**Installation:**
1. Admin Dashboard → Package Manager → Upload Package
2. Select `manifest.json` or browse package directory
3. Click **Validate Package** (green badge 5% → 100% ✅)
4. Review validation report
5. Click **Install**
6. Configure settings (driver logging, departments, campuses, lead times)
7. Create trip categories (11, 23, 34, 36, 41 codes)
8. Create maintenance templates (Bus Template, Truck Template, etc.)
9. Add vehicles and apply templates
10. Assign roles to users (vm_driver, vm_crew, vm_fm, vm_md, vm_admin)

**Documentation:**
- [Package README](vehicle-maintenance/README.md) - Full feature list and usage guide
- [CHANGELOG](vehicle-maintenance/CHANGELOG.md) - Version history
- [LICENSE](vehicle-maintenance/LICENSE) - Proprietary license

---

## Planned Packages

### 🚌 Bus Route Management
- Route planning and optimization
- Stop scheduling
- Driver assignments
- Student rider tracking
- GPS integration

### ⛽ Fuel Tank Management
- Tank level monitoring
- Delivery scheduling
- Consumption tracking
- Supplier management

### 🔧 Parts Inventory
- Parts catalog
- Stock levels
- Reorder points
- Vendor tracking
- Purchase history

### 📋 DOT Compliance
- Vehicle inspections
- Driver logs
- Compliance reporting
- Certificate tracking
- Violation management

## Contributing

See [CONTRIBUTING.md](../../../CONTRIBUTING.md) for guidelines on submitting fleet management packages.
