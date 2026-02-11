# Fleet Management Packages

Vehicle fleet tracking, fuel logging, maintenance scheduling, and driver management tools.

## Packages

### 🚗 Vehicle Maintenance & Fleet Tracking v2.1.0
**Directory:** `vehicle-maintenance/`

Hub/Management separated fleet management with vehicle tracking, fuel logging, and maintenance scheduling. **Layer 2 compliant** with audit-grade manager oversight, field-level edit boundaries, and formal workflow state machines.

**Features:**
- **Hub Cards (User-Facing):**
  - Fleet Management: View district fleet roster
  - Fuel & Trip Tracking: Log fuel trips and view history
  - Maintenance Tracking: Log maintenance events and view history
- **Management Sections (Admin):**
  - Full vehicle CRUD with departments and campuses
  - Fuel analytics and district-wide reporting
  - Maintenance templates, schedules, and analytics
  - Trip categories configuration (11, 23, 34, 36, 41)
  - Package settings and configuration
- Template-based maintenance scheduling (mileage + time intervals)
- Auto-calculated next-due maintenance dates
- Out-of-service vehicle status tracking
- File uploads (fuel receipts, maintenance invoices, photos)
- Role-based access control (3 roles: vm_user, vm_manager, vm_admin)
- **Layer 2 Compliance:** Workflow states, manager edit boundaries, audit events with required fields

**Database Entities:** 12 tables (vehicles, trip_categories, fuel_logs, maintenance_items, maintenance_events, maintenance_templates, template_items, vehicle_schedules, departments, campuses, settings, audit_logs)

**Modules:** Hub cards (3) + Management sections (4) with subsections

**Tags:** `operations`, `fleet`, `vehicles`, `maintenance`, `fuel`, `hub-management`

**For:** Transportation departments, maintenance crews, fleet managers, all Hub users

**Requirements:**
- Hub >=1.0.0 <3.0.0
- PHP >=8.0
- MySQL >=5.7
- Tested on Hub 2.0.0

**Installation:**
1. Admin Dashboard → Package Manager → Browse Package Repository
2. Select "Vehicle Maintenance & Fleet Tracking" v2.1.0
3. Click **Download**
4. System validates package automatically (includes Layer 2 compliance checks)
5. Click **Install**
6. Configure trip categories in Management → Configuration
7. Set up maintenance items and templates
8. Add vehicles and assign to departments/campuses
9. Assign roles: vm_user (Hub users), vm_manager (Fleet managers), vm_admin (Full access)

**Documentation:**
- [Package README](vehicle-maintenance/README.md) - Full feature list and usage guide
- [CHANGELOG](vehicle-maintenance/CHANGELOG.md) - Version history
- [LICENSE](vehicle-maintenance/LICENSE) - Proprietary license
- [Database Schema](vehicle-maintenance/database/schema.sql) - Complete DDL
- [Seed Data](vehicle-maintenance/database/seed.sql) - Default trip categories and maintenance items

**Previous Versions:**
- v2.0.0 - Hub/Management separation (no Layer 2 compliance)
- v1.0.0 archived in [`archive/operations/fleet/vehicle-maintenance/`](../../archive/operations/fleet/vehicle-maintenance/)

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
