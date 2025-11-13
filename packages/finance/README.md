# Finance Packages

Financial management tools for school districts, including reimbursements, budgeting, expense tracking, and fiscal reporting.

## 💰 Available Packages

### Reimbursement Request & Fuel Tracking
**Version:** 1.0.0  
**Category:** finance  
**Path:** `finance/reimbursement-request`

Unified reimbursement system for monetary expenses and fuel trip tracking with approval workflows, fiscal year tracking, and comprehensive analytics.

**Key Features:**
- Monetary reimbursement requests with receipt uploads
- Fuel trip logging with automatic gallon calculations
- 6-state approval workflow (submitted → paid)
- Configurable fiscal year tracking
- Analytics dashboards (spend by category, fuel trends)
- Role-based access (submitter, supervisor, BM, admin)
- Notification system for status changes

**Requirements:**
- Hub: >=1.0.0 <2.0.0
- PHP: >=8.0
- MySQL: >=5.7

**[View Documentation →](reimbursement-request/README.md)**

---

## 📋 Planned Packages

### Budget Tracking & Forecasting
Track departmental budgets, monitor expenditures, forecast spending, and generate variance reports.

### Purchase Order Management
Create, approve, and track purchase orders with multi-level approval workflows and vendor management.

### Vendor Payment Tracking
Manage vendor invoices, payment schedules, and compliance documentation.

### Grant Management
Track grant applications, funding sources, expenditures, and compliance reporting requirements.

### Payroll Reimbursements
Specialized reimbursement workflows for payroll-related expenses with direct deposit integration.

---

## 🎯 Category Scope

The finance category encompasses tools for:
- **Expense Management**: Reimbursements, purchase orders, vendor payments
- **Budget Planning**: Forecasting, variance analysis, departmental allocations
- **Fiscal Compliance**: Grant tracking, audit trails, reporting
- **Analytics**: Spend patterns, budget utilization, cost centers
- **Workflow Automation**: Multi-level approvals, notifications, status tracking

---

## 🚀 Contributing

To contribute a finance package:
1. Follow the [Package Specification V2.0](https://github.com/R1CH4RD25/TheHub/blob/main/docs/PACKAGE_SPECIFICATION_V2.md)
2. Use the `reimb_` namespace prefix for database entities (or request a new prefix)
3. Implement appropriate role-based access control
4. Include comprehensive documentation (README, CHANGELOG, LICENSE)
5. Add 2+ screenshots demonstrating key features
6. Submit a pull request to this repository

---

## 📞 Support

For questions about finance packages:
- **Repository:** https://github.com/R1CH4RD25/TheHub
- **Issues:** https://github.com/R1CH4RD25/TheHub/issues
- **Contact:** tech@woodsonisd.net
