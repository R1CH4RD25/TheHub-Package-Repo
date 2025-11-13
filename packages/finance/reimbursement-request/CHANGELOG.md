# Changelog

All notable changes to the Reimbursement Request & Fuel Tracking package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-11-13

### Added
- **Initial release of Reimbursement Request & Fuel Tracking package**
- 3 database entities:
  - `reimb_monetary_request`: Monetary reimbursement requests with workflow
  - `reimb_fuel_trip`: Fuel trip records with gallons earned/claimed tracking
  - `reimb_settings`: Package-wide configuration (fiscal year, notifications)
- 11 modules:
  - **Forms** (4): monetary-request-form, fuel-trip-form, reimbursement-settings, monetary-request-detail, fuel-trip-detail
  - **TableViews** (4): my-requests, my-fuel, admin-reimbursement-dashboard, admin-fuel-dashboard
  - **Workflow** (1): reimbursement-workflow (6-state approval process)
  - **Analytics** (1): reimbursement-analytics (spend and fuel trends)
- 4 roles:
  - `reimb_submitter`: Submit monetary/fuel requests, view own records
  - `reimb_supervisor`: Approve/deny requests, view all submissions
  - `reimb_bm`: Mark paid, access fuel ledger, view analytics
  - `reimb_admin`: Full control, configure settings
- 6-state approval workflow:
  - submitted → reviewing → needs_info → approved → denied → paid
  - Conditional actions based on current status
  - Role-based workflow advancement
- Fiscal year tracking:
  - Configurable start date (month + day)
  - Automatic fiscal year assignment to all trips
  - Year-to-date gallons earned/claimed per user
- Analytics dashboards:
  - Spend by category (bar chart, current fiscal year)
  - Monthly spend trends (line chart)
  - Gallons earned by user (bar chart)
  - Gallons claimed over time (line chart)
- Notification system:
  - 6 configurable triggers (submit, approve, deny, needs info, supervisor, BM)
  - Email notifications to BM and admin
  - Toggle notifications on/off per trigger
- Receipt support:
  - Upload receipts (PDF/JPG/PNG, 10MB max)
  - Physical receipt option (turn in later)
  - Pump photo uploads for fuel receipts
- Export functionality:
  - CSV export for all tables
  - Excel (.xlsx) export support
- Gallon calculation:
  - Standard: Mileage ÷ 20 MPG
  - With trailer: Mileage ÷ 12 MPG
  - Automatic calculation on submission

### Security
- Field validation:
  - Expense date: maxDate = today
  - Amount: $0.01 - $10,000.00
  - Trip miles: 1 - 2,000 miles
  - Gallons claimed: 0 - 500 gallons
  - Notes: max 2,000 characters
- File upload restrictions:
  - 10MB max file size
  - PDF, JPG, JPEG, PNG only
  - Mime type enforcement (application/pdf, image/jpeg, image/png)
- Rate limiting:
  - Monetary request form: 10/user total, 5/minute, 20/hour
  - Fuel trip form: 20/user total, 10/minute, 40/hour
  - Settings form: 5/user total, 2/minute, 10/hour
- SQL injection prevention:
  - Prepared statements for all database queries
  - ULID validation (26 characters, alphanumeric)
  - Foreign key constraints for referential integrity
- XSS protection:
  - Output escaping via `htmlspecialchars()`
  - CSP headers (Hub core)
  - No inline JavaScript in modules

### Compatibility
- Hub Version: >=1.0.0 <2.0.0
- Tested up to: Hub 1.3.0
- PHP: >=8.0
- MySQL: >=5.7

### Documentation
- Complete README.md (1,000+ lines)
  - Installation guide (4 steps)
  - Usage by role (submitter, supervisor, BM, admin)
  - Database schema reference
  - Permissions matrix (4 roles x 8 actions)
  - Module reference (11 modules)
  - Security & validation details
  - Troubleshooting guide (5 common issues)
  - Roadmap (v1.1, v1.2, v2.0)
- CHANGELOG.md (semantic versioning)
- LICENSE (Proprietary - Woodson ISD)

### Known Limitations
- Fiscal year changes only apply to new trips (existing trips retain original fiscal year)
- Mileage rates are fixed (20 MPG standard, 12 MPG with trailer) - configurable in v1.1
- No offline mode - requires active internet connection
- Single file upload per request (multiple files planned for v1.2)

## [Unreleased]

### Planned for v1.1
- Configurable mileage rates (adjustable MPG)
- Google Maps API integration for automatic mileage calculation
- Recurring fuel trip templates
- Email template customization
- Dashboard widgets for home page

### Planned for v1.2
- Mobile-optimized fuel logging
- Offline mode with synchronization
- OCR receipt scanning
- Budget allocation tracking
- Multi-currency support
- Multiple file uploads per request

### Planned for v2.0
- Purchase order integration
- Payroll system export
- Automatic reimbursement via direct deposit
- Advanced reporting (variance analysis, forecasting)
- District-wide budget dashboards

---

[1.0.0]: https://github.com/R1CH4RD25/TheHub-Package-Repo/releases/tag/reimbursement-request-v1.0.0
