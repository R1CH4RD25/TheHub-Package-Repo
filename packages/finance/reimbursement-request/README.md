# Reimbursement Request & Fuel Tracking Package

**Version:** 1.0.0  
**Author:** Woodson ISD Technology Department  
**License:** Proprietary  
**Category:** Finance

## Overview

Unified reimbursement system for monetary expenses and fuel trips, including year-to-date tracking, role-based approval workflows, configurable fiscal years, and comprehensive analytics. Designed for school districts to manage employee reimbursements and fuel gallons efficiently.

## Features

### 💰 Monetary Reimbursement Requests
- Submit expense reimbursement requests with receipt uploads
- Multiple expense categories (meals, supplies, travel, student activities)
- Physical receipt option (turn in later)
- Status tracking (submitted → reviewing → approved → paid)
- Edit capability before payment
- Supervisor and Business Manager approval workflow
- Payment tracking with reference numbers

### ⛽ Fuel Trip Tracking
- Log fuel trips with mileage and destination
- Calculate "gallons earned" based on mileage
- Optional trailer tracking (impacts gallon calculation)
- Claim gallons immediately or later
- Receipt uploads for fuel purchases (pump photos accepted)
- Category tracking (athletics, field trips, training, student transport)
- Year-to-date gallons earned/claimed per user

### 🔄 Approval Workflow
- **Submitted** → **Reviewing** → **Needs Info** / **Approved** / **Denied** → **Paid**
- Role-based workflow advancement (supervisors approve, BMs mark paid)
- Conditional actions based on current status
- Workflow visualization and history

### 📊 Analytics & Reporting
- Spend by category (current fiscal year)
- Monthly spend trends
- Gallons earned by user
- Gallons claimed over time
- Export all data to CSV/Excel

### ⚙️ Configurable Settings
- Fiscal year start date (month + day)
- Physical receipt allowance toggle
- Notification preferences (submitter, supervisor, BM)
- Business Manager and Admin notification emails

### 📱 User Experience
- Personal dashboards (My Requests, My Fuel)
- Admin dashboards for supervisors and BMs
- Sortable, filterable tables
- Conditional field visibility (showIf logic)
- Rate limiting per user/minute/hour

## Requirements

### Hub Core
- Hub Version: >=1.0.0 <2.0.0
- Tested up to: 1.3.0

### Server Environment
- PHP: 8.0 or higher
- MySQL: 5.7 or higher
- Disk Space: 500MB (for receipt uploads)

### Dependencies
- Core Hub authentication system
- ULID library for unique identifiers
- File upload system with validation
- Notification system (email)

## Installation

### 1. Upload Package
- Navigate to **Admin → Packages** in the Hub
- Click **Upload Package**
- Select the `reimbursement-request` package manifest or directory
- System will validate structure and compatibility

### 2. Database Migration
The package will automatically create the following tables:
- `reimb_monetary_request` - Monetary reimbursement requests
- `reimb_fuel_trip` - Fuel trip records with gallons earned/claimed
- `reimb_settings` - Package-wide configuration

### 3. Initial Configuration
After installation:
1. Navigate to **Settings** (reimb_admin role required)
2. Configure:
   - Fiscal year start date (default: September 1)
   - Allow physical receipts (default: enabled)
   - Notification preferences (default: all disabled)
   - Business Manager email for notifications
   - Admin notification email

### 4. Assign Roles
Navigate to **Admin → Users** and assign package roles:
- Submitters: `reimb_submitter` (staff, teachers)
- Supervisors: `reimb_supervisor` (principals, department heads)
- Business Manager: `reimb_bm`
- Package Administrator: `reimb_admin`

## Usage

### For Submitters (reimb_submitter)

#### Submit Monetary Reimbursement
1. Navigate to **Submit Monetary Request**
2. Enter expense date (must be in the past)
3. Select category (meals, supplies, travel meals, student activity, other)
4. Enter amount ($0.01 - $10,000)
5. Enter vendor/payee name (optional)
6. Choose receipt type:
   - **Upload Now**: Upload PDF/JPG/PNG up to 10MB
   - **Will Turn In Physical Receipt**: Submit later
7. Add notes/justification (optional, up to 2,000 characters)
8. Submit request

**Status Updates:**
- **Submitted**: Request received, awaiting supervisor review
- **Reviewing**: Supervisor is reviewing
- **Needs Info**: Supervisor needs more information (edit and resubmit)
- **Approved**: Approved, awaiting payment from BM
- **Denied**: Request denied (view reason in notes)
- **Paid**: Payment issued

#### Log Fuel Trip
1. Navigate to **Log Fuel Trip**
2. Enter trip date
3. Enter destination
4. Check "With Trailer?" if applicable (impacts gallon calculation)
5. Enter round-trip mileage (1-2,000 miles)
6. Select trip category
7. Add notes (optional)
8. If claiming fuel now:
   - Check "Acquiring Fuel at this time?"
   - Enter gallons being received
   - Select receipt type (upload, physical, none)
   - If uploading, attach receipt/pump photo

**Gallons Calculation:**
- Standard: Mileage ÷ 20 MPG = Gallons Earned
- With Trailer: Mileage ÷ 12 MPG = Gallons Earned
- Example: 100 miles without trailer = 5 gallons earned
- Example: 100 miles with trailer = 8.33 gallons earned

### For Supervisors (reimb_supervisor)

1. Navigate to **Reimbursement Dashboard**
2. View all submitted requests
3. Filter by status, category, date range
4. Actions available:
   - **View**: See full request details
   - **Approve**: Move to "Approved" status (awaiting BM payment)
   - **Deny**: Deny request with reason
   - **Needs Info**: Request more information from submitter

### For Business Managers (reimb_bm)

#### Monetary Reimbursements
1. Navigate to **Reimbursement Dashboard**
2. Filter by status = "Approved"
3. Actions:
   - **View**: Review request details and receipts
   - **Mark Paid**: Enter payment date and reference, move to "Paid" status

#### Fuel Ledger
1. Navigate to **Fuel Dashboard**
2. View all fuel trips across all users
3. Monitor gallons earned vs. gallons claimed
4. Filter by user, date range, category
5. Export for accounting reconciliation

#### Analytics
1. Navigate to **Analytics**
2. **Monetary Analytics Tab**:
   - Spend by Category (current fiscal year)
   - Monthly Spend Trends
3. **Fuel Analytics Tab**:
   - Gallons Earned by User
   - Gallons Claimed Over Time

### For Administrators (reimb_admin)

- Full access to all features
- Configure settings (fiscal year, notifications)
- Manage fuel trips (remove if needed)
- Access all dashboards and analytics

## Database Schema

### Entities Overview
| Entity | Primary Purpose | Key Fields |
|--------|----------------|------------|
| `reimb_monetary_request` | Monetary reimbursements | expense_date, category, amount, status, receipt_file_id, paid_date |
| `reimb_fuel_trip` | Fuel trip records | trip_date, destination, trip_miles, gallons_earned, gallons_claimed, fiscal_year_start |
| `reimb_settings` | Package configuration | fiscal_year_start_month, fiscal_year_start_day, notification toggles |

### Indexes
- **Monetary Requests**: tenant, requester+status, status+expense_date, status+paid_date
- **Fuel Trips**: tenant, user+trip_date, category+trip_date
- **Settings**: unique tenant

## Permissions Matrix

| Role | Submit Request | Submit Fuel | View Own | Edit Own | Approve | Mark Paid | Analytics | Settings |
|------|---------------|-------------|----------|----------|---------|-----------|-----------|----------|
| reimb_submitter | ✅ | ✅ | ✅ | ✅ (if not paid) | ❌ | ❌ | ❌ | ❌ |
| reimb_supervisor | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ (read-only) | ❌ |
| reimb_bm | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| reimb_admin | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

## Module Reference

### Forms (4)
1. **monetary-request-form**: Submit monetary reimbursement
2. **fuel-trip-form**: Log fuel trip
3. **reimbursement-settings**: Configure package (admin only)
4. **monetary-request-detail**: View request details (read-only)
5. **fuel-trip-detail**: View fuel trip details (read-only)

### TableViews (4)
1. **my-requests**: Personal monetary requests (submitters)
2. **my-fuel**: Personal fuel trips (submitters)
3. **admin-reimbursement-dashboard**: All monetary requests (supervisors/BMs)
4. **admin-fuel-dashboard**: All fuel trips (BMs)

### Workflow (1)
1. **reimbursement-workflow**: 6-step approval workflow

### Analytics (1)
1. **reimbursement-analytics**: Spend and fuel analytics (BMs)

## Security & Validation

### Input Validation
- **Amount**: $0.01 - $10,000.00
- **Trip Miles**: 1 - 2,000 miles
- **Gallons Claimed**: 0 - 500 gallons
- **Notes**: Max 2,000 characters
- **Expense Date**: Must be today or earlier
- **Trip Date**: Must be today or earlier

### File Upload Validation
- **Formats**: PDF, JPG, JPEG, PNG
- **Max Size**: 10MB per file
- **Mime Types**: Enforced (application/pdf, image/jpeg, image/png)

### Rate Limiting
- **Monetary Request Form**: 10/user total, 5/minute, 20/hour
- **Fuel Trip Form**: 20/user total, 10/minute, 40/hour
- **Settings Form**: 5/user total, 2/minute, 10/hour

### SQL Injection Prevention
- Prepared statements for all queries
- ULID validation (26 chars, alphanumeric)
- Foreign key constraints enforce referential integrity

### XSS Protection
- Output escaping via `htmlspecialchars()`
- CSP headers in Hub core
- No inline JavaScript in forms

## Troubleshooting

### "Status not changing after approval"
- Verify supervisor has `reimb_supervisor` role
- Check workflow permissions in role matrix
- Review application logs for workflow errors

### "Gallons earned calculation seems wrong"
- Without trailer: Miles ÷ 20 = Gallons
- With trailer: Miles ÷ 12 = Gallons
- Check if "With Trailer?" was checked during submission

### "Cannot mark request as paid"
- Only BMs (`reimb_bm`) can mark paid
- Request must be in "Approved" status
- Check role assignment in Admin → Users

### "Fiscal year dates not updating"
- Settings require `reimb_admin` role
- Changes apply to new trips only (existing trips retain original fiscal year)
- Clear browser cache after saving settings

### "Receipt upload failing"
- Check file size (max 10MB)
- Verify file format (PDF, JPG, JPEG, PNG only)
- Ensure `uploads/` directory has write permissions (775)
- Review `logs/php-errors.log` for specific error

## Support

- **Repository:** https://github.com/R1CH4RD25/TheHub
- **Issues:** https://github.com/R1CH4RD25/TheHub/issues
- **Documentation:** https://github.com/R1CH4RD25/TheHub-Package-Repo/tree/main/packages/finance/reimbursement-request
- **Contact:** tech@woodsonisd.net

## Roadmap

### Version 1.1 (Planned)
- Mileage rate configuration (adjustable MPG for calculations)
- Automatic mileage calculation via Google Maps API
- Recurring fuel trip templates
- Notification email customization
- Dashboard widgets for home page

### Version 1.2 (Planned)
- Mobile-optimized fuel logging
- Offline mode with sync
- OCR receipt scanning
- Budget allocation tracking
- Multi-currency support

### Version 2.0 (Future)
- Purchase order integration
- Payroll system export
- Automatic reimbursement via direct deposit
- Advanced reporting (variance, forecasting)
- District-wide budget dashboards

## License

Proprietary software developed for Woodson ISD. All rights reserved.

## Credits

Developed by the Woodson ISD Technology Department as part of The Hub modular platform initiative.
