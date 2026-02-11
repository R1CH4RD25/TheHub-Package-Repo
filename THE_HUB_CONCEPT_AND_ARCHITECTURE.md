# The Hub: Modular School District Platform
**Comprehensive Overview for External Auditors**

**Document Version:** 1.0
**Last Updated:** February 11, 2026
**Author:** Woodson ISD Technology Department
**Status:** Active Development

---

## 📋 Table of Contents

1. [Executive Summary](#executive-summary)
2. [The Hub Concept](#the-hub-concept)
3. [System Architecture](#system-architecture)
4. [Dual-Repository Model](#dual-repository-model)
5. [User Workflow](#user-workflow)
6. [Package Ecosystem](#package-ecosystem)
7. [Security & Compliance](#security--compliance)
8. [Technical Infrastructure](#technical-infrastructure)
9. [Future Roadmap](#future-roadmap)

---

## Executive Summary

**The Hub** is a modular, enterprise-grade web application platform designed for Woodson Independent School District to consolidate and streamline administrative operations. Unlike traditional monolithic systems, The Hub uses a **dual-repository architecture** that separates the core platform from its extensible package ecosystem, enabling secure, validated, and independently managed functionality modules.

### Key Facts
- **Primary Purpose:** Unified platform for district operations (fleet management, reporting, reimbursements, workflows)
- **Architecture:** Modular with package-based extensibility
- **Users:** District staff, administrators, students (role-based)
- **Technology:** PHP 8.3+, MySQL 8.0+, OAuth 2.0 authentication
- **Repositories:** 2 (Core Platform + Package Repository)
- **Current Status:** Active development, not yet in production

---

## The Hub Concept

### Vision
Create a single, centralized platform ("The Hub") where district staff access all administrative tools through one login, one interface, and one consistent user experience—while maintaining complete modularity and extensibility.

### Core Principles

#### 1. **Single Sign-On Experience**
Users log in once via Google OAuth or Microsoft Azure AD and gain access to all authorized modules based on their role.

#### 2. **Modular Architecture**
Each functional area (vehicle maintenance, bullying reports, reimbursements) is a **self-contained package** that can be:
- Installed independently
- Updated without affecting other modules
- Removed cleanly if no longer needed
- Developed by different teams

#### 3. **Three-Layer Operational Model**
The Hub separates concerns into three distinct operational layers:

| Layer | Purpose | Who Uses It | Key Capabilities |
|-------|---------|-------------|------------------|
| **Hub Layer** | Data submission & recording | End users (staff, students) | Submit forms, view own records, access resources |
| **Management Layer** | Operational oversight | Assigned managers/supervisors | View submissions, **edit records**, run reports, correct errors |
| **Administrator Layer** | Platform governance | System administrators | Install packages, configure roles, manage permissions, system settings |

**Critical Distinction:**
- **Management Layer** = Operational oversight (business process management)
- **Administrator Layer** = Platform governance (system configuration)

#### 4. **Role-Based Access Control**
Granular permissions at three levels:
- **Global Roles:** Admin, Super Admin, Staff, Student, Manager
- **Section Access:** Which modules a user can see
- **Module Permissions:** What actions they can perform within each module

#### 5. **Hub, Management, & Admin Separation**
- **Hub Interface:** User-facing tools for daily tasks (submit forms, view own data)
- **Management Console:** Operational oversight (review submissions, edit records, run reports)
- **Admin Dashboard:** Platform governance (install packages, configure system, manage users)

---

## System Architecture

### Three-Layer Operational Model

The Hub implements a **three-layer operational architecture** that separates data submission, operational oversight, and platform governance into distinct access levels with clear boundaries.

#### Layer 1: Hub (End User Frontend)
**Purpose:** Data submission and record viewing
**Who Uses It:** End users (staff, students, drivers)
**Capabilities:**
- Submit records via quick-access card objects
- View own submissions and history
- Upload supporting documents (receipts, photos)
- Track submission status
- Fast, focused workflows designed for daily operations

**Access Pattern:**
```
User logs in → hub.php → Click module card → Submit form → Confirmation
```

**Examples:**
- Driver submits fuel log
- Staff member submits bullying incident report
- Teacher submits reimbursement request

#### Layer 2: Management (Operational Oversight)
**Purpose:** Review, edit, report on submitted data
**Who Uses It:** Assigned managers, supervisors, department heads
**Capabilities:**
- ✅ View all submitted records in their domain
- ✅ Edit/correct submitted data (with audit trail)
- ✅ Run reports and analytics
- ✅ Approve/reject workflow submissions
- ✅ Export data to Excel/CSV
- ❌ Cannot install packages
- ❌ Cannot modify system configuration
- ❌ Cannot change user roles or permissions

**Manager Role Classes:**
- `management_crew` - Operational access to specific modules
- `management_director` - Department-wide oversight
- `management_fleet_manager` - Domain-specific management
- `management_supervisor` - Approval authority

**Access Pattern:**
```
Manager logs in → Management Console → Select module → View/edit records → Audit logged
```

**Key Difference from Hub Users:**
- Hub users → Submit own records
- Managers → Review/edit all records in their domain

**Audit Trail Protection:**
Every manager edit creates an audit log entry with:
- Who made the change (manager ID)
- What was changed (before/after values)
- When it was changed (timestamp)
- Why it was changed (optional reason field)
- Original submitter preserved

#### Layer 3: Administrator (Platform Governance)
**Purpose:** System setup, configuration, access control
**Who Uses It:** IT administrators, super admins
**Capabilities:**
- ✅ Install/remove packages
- ✅ Assign user roles and permissions
- ✅ Configure sections and access control
- ✅ Manage site settings and branding
- ✅ Review audit logs
- ✅ Backup/restore system
- ❌ Should not routinely edit operational data

**Access Pattern:**
```
Admin logs in → Admin Dashboard → Configure system → Changes reflected system-wide
```

**Critical Distinction:**
- **Managers** = Operational oversight (edit data within their domain)
- **Administrators** = Platform governance (configure the system itself)

---

### Operational Layer Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                  LAYER 3: ADMINISTRATOR                          │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  Admin Dashboard (Platform Governance)                     │ │
│  │  • Install packages                                        │ │
│  │  • Configure roles and permissions                         │ │
│  │  • Manage site settings                                    │ │
│  │  • Review audit logs                                       │ │
│  └────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                  LAYER 2: MANAGEMENT                             │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  Management Console (Operational Oversight)                │ │
│  │  • View all submissions in domain                          │ │
│  │  • Edit/correct submitted records                          │ │
│  │  • Run analytics and reports                               │ │
│  │  • Approve/reject workflows                                │ │
│  │  ✓ All edits logged to audit trail                         │ │
│  └────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                  LAYER 1: HUB (END USERS)                        │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  Hub Interface (Data Submission)                           │ │
│  │  • Submit records via module cards                         │ │
│  │  • View own submission history                             │ │
│  │  • Upload supporting documents                             │ │
│  │  • Track submission status                                 │ │
│  └────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      DATABASE LAYER                              │
│  • User submissions (with original submitter preserved)         │
│  • Edit history (before/after snapshots)                        │
│  • Audit trail (who/what/when for all mutations)                │
└─────────────────────────────────────────────────────────────────┘
```

---

### Technical Layer Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    PRESENTATION LAYER                    │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐ │
│  │  Hub Page    │  │    Admin     │  │  Management  │ │
│  │ (User Tools) │  │  Dashboard   │  │   Console    │ │
│  └──────────────┘  └──────────────┘  └──────────────┘ │
└─────────────────────────────────────────────────────────┘
                          │
┌─────────────────────────────────────────────────────────┐
│                    APPLICATION LAYER                     │
│  ┌──────────────────────────────────────────────────┐  │
│  │           Core Platform (TheHub Repo)            │  │
│  │  • Authentication (OAuth 2.0)                    │  │
│  │  • Authorization (Role System)                   │  │
│  │  • Package Manager (Install/Update/Remove)       │  │
│  │  • Theme Engine (Customization)                  │  │
│  │  • Audit Logging (Compliance)                    │  │
│  └──────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────┐  │
│  │      Installed Packages (From Package Repo)      │  │
│  │  • Vehicle Maintenance                           │  │
│  │  • Bullying Report System                        │  │
│  │  • Reimbursement Workflows                       │  │
│  │  • [Future Packages...]                          │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
                          │
┌─────────────────────────────────────────────────────────┐
│                      DATA LAYER                          │
│  ┌──────────────────────────────────────────────────┐  │
│  │              MySQL Database                       │  │
│  │  • Users & Permissions                           │  │
│  │  • Sections & Modules                            │  │
│  │  • Package Data (per-package tables)             │  │
│  │  • Audit Logs                                    │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

### Core Components

#### Authentication System (`src/Auth.php`)
- Google OAuth 2.0 (primary)
- Microsoft Azure AD OAuth (secondary)
- Local username/password (optional fallback)
- Domain restrictions (e.g., `@woodsonisd.net`)
- Google Groups integration for auto-approval
- Session management with domain lock

#### Package Manager (`src/PackageManager.php`)
- Install packages from `.hubpkg` files
- Validate package structure and compatibility
- Create database tables automatically
- Manage section access and permissions
- Update/uninstall packages safely

#### Role Management (`src/Roles.php`, `SectionRoleAccess.php`)
- **3-Layer Role Hierarchy:**
  - **Layer 1 (Hub):** `staff`, `student` - Submit data, view own records
  - **Layer 2 (Management):** `manager`, `director`, `supervisor` - View/edit submissions, run reports
  - **Layer 3 (Admin):** `admin`, `super_admin` - Platform configuration, system governance
- Section-level access control
- Module-specific permissions with edit authority
- "View As" capability for super admins (operational testing)
- **Manager Role Definition:**
  - ✅ Can view all submissions within assigned modules
  - ✅ Can edit/correct submitted records (with audit trail)
  - ✅ Can run operational reports and analytics
  - ✅ Can approve/deny workflow requests
  - ❌ Cannot install packages or modify system configuration
  - ❌ Cannot alter RBAC settings or assign roles
  - ❌ Cannot modify audit logs or system settings

#### Layout System (`src/Layout.php`)
- Enterprise Microsoft 365-style design
- Responsive navigation
- Theme customization (45+ color settings)
- Consistent header/footer across all modules

---

## Dual-Repository Model

The Hub uses **two separate GitHub repositories** with distinct purposes:

### Repository 1: TheHub (Core Platform)
**URL:** https://github.com/R1CH4RD25/TheHub
**Branch:** `laravel-migration` (active development)
**Purpose:** Core platform infrastructure

#### Contains:
- **Authentication & Authorization** - User login, role management
- **Package Management System** - Install/update/remove packages
- **Admin Dashboard** - User management, settings, audit logs
- **Management Console** - Administrative tools and reporting
- **Theme Engine** - Branding and customization
- **Database Infrastructure** - Core tables (users, sections, modules)
- **API Endpoints** - Internal APIs for AJAX operations
- **Layout Components** - Shared UI components (header, footer, sidebar)

#### Key Files:
```
/var/www/woodson/thehub/
├── public/                  # Web root
│   ├── hub.php             # Module selector (landing page)
│   ├── admin/              # Admin dashboard pages
│   ├── management/         # Management console pages
│   └── api/                # API endpoints
├── src/                     # Core PHP classes (PSR-4)
│   ├── Auth.php            # Authentication
│   ├── PackageManager.php  # Package installation
│   ├── Database.php        # Database singleton
│   └── Layout.php          # UI rendering
├── database/               # Core schema migrations
└── tests/                  # PHPUnit test suite
```

#### Responsibilities:
- ✅ User authentication and session management
- ✅ Role-based access control
- ✅ Package discovery, validation, installation
- ✅ Navigation and routing
- ✅ Audit logging for compliance
- ✅ Theme and branding customization
- ❌ Does NOT contain business logic for specific modules

---

### Repository 2: TheHub-Package-Repo (Package Ecosystem)
**URL:** https://github.com/R1CH4RD25/TheHub-Package-Repo
**License:** MIT (open for community contributions)
**Purpose:** Validated package library

#### Contains:
- **Package Files** - Pre-built `.hubpkg` packages
- **Package Documentation** - README, CHANGELOG for each package
- **Screenshots** - Visual demonstrations of packages
- **Package Categories** - Organized by function (Operations, Finance, Student, etc.)
- **Contributing Guidelines** - How to submit packages
- **Package Specification** - Technical requirements for packages

#### Directory Structure:
```
TheHub-Package-Repo/
├── packages/
│   ├── operations/          # Fleet, facilities, maintenance
│   │   └── fleet/
│   │       └── vehicle-maintenance/
│   │           ├── .hubpkg
│   │           ├── README.md
│   │           ├── CHANGELOG.md
│   │           └── screenshots/
│   ├── finance/             # Reimbursements, budgeting
│   │   └── reimbursement-request/
│   ├── reporting/           # Incident reports, compliance
│   │   └── bullying-report/
│   ├── student/             # Student-facing tools
│   ├── forms/               # Custom form builders
│   ├── workflows/           # Approval processes
│   └── analytics/           # Dashboards and reports
├── archive/                 # Old package versions
├── CONTRIBUTING.md          # Submission guidelines
└── README.md                # Package catalog
```

#### Current Packages (February 2026):
1. **Vehicle Maintenance & Fleet Tracking** `v1.0.0`
   - **Layer 1 (Hub):** Staff submit fuel logs and maintenance records
   - **Layer 2 (Management):** Managers view/edit all entries, run fleet reports
   - **Layer 3 (Admin):** Configure trip categories, maintenance templates, settings

2. **Bullying Report System** `v1.0.0`
   - **Layer 1 (Hub):** Students/staff submit anonymous incident reports
   - **Layer 2 (Management):** Counselors/principals review, edit, track investigations
   - **Layer 3 (Admin):** Configure form fields, notification settings

3. **Reimbursement Request & Fuel Tracking** `v1.0.0`
   - **Layer 1 (Hub):** Staff submit reimbursement requests and fuel trips
   - **Layer 2 (Management):** Supervisors approve/deny, business managers mark paid, edit errors
   - **Layer 3 (Admin):** Configure fiscal year, approval workflows, notification settings
- ✅ Store validated, production-ready packages
- ✅ Provide package documentation and examples
- ✅ Accept community contributions via pull requests
- ✅ Version control for package releases
- ✅ Showcase package capabilities with screenshots
- ❌ Does NOT install packages (that's TheHub's job)

---

## User Workflow

### 1. Authentication Flow
```
┌─────────────┐
│ User visits │
│ hub.woodson │
│  isd.net    │
└──────┬──────┘
       │
       ▼
┌─────────────┐
│ Login Page  │
│ - Google    │──────────────┐
│ - Microsoft │              │
└──────┬──────┘              │
       │                     │
       ▼                     ▼
┌─────────────┐    ┌──────────────────┐
│ OAuth Flow  │    │ Domain Check     │
│ (Google/MS) │───▶│ @woodsonisd.net  │
└──────┬──────┘    └────────┬─────────┘
       │                    │
       ▼                    ▼
┌─────────────┐    ┌──────────────────┐
│ Get User    │    │ Check Invitation │
│ Profile     │───▶│ or Auto-Approve  │
└──────┬──────┘    └────────┬─────────┘
       │                    │
       ▼                    ▼
┌─────────────┐    ┌──────────────────┐
│ Load Roles  │    │ Create Session   │
│ & Modules   │───▶│ Redirect to Hub  │
└─────────────┘    └──────────────────┘
```

### 2. Module Access Flow (3-Layer Model)
```
User arrives at hub.php
       │
       ▼
Query: What modules can this user access?
       │
       ├─── 0 modules → "Contact admin" message
       │
       ├─── 1 module  → Auto-redirect to that module
       │
       └─── 2+ modules → Show module selector cards
                            │
                            ▼
                    User clicks a card
                            │
                            ▼
              Check user's role for this module
                            │
                ┌───────────┼───────────┐
                │           │           │
                ▼           ▼           ▼
         ┌────────┐  ┌──────────┐  ┌─────────┐
         │ Layer 1│  │ Layer 2  │  │ Layer 3 │
         │  Hub   │  │Management│  │  Admin  │
         └────────┘  └──────────┘  └─────────┘
              │           │              │
              ▼           ▼              ▼
         Submit      View/Edit      Configure
         forms       records        system
              │           │              │
              └───────────┴──────────────┘
                          │
                          ▼
              All mutations logged in audit_logs
```

### 3. Package Installation Workflow (Admin Only)
```
Admin logs in
       │
       ▼
Admin Dashboard → Packages Tab
       │
       ├─── Browse Package Repository (GitHub)
       │           │
       │           ▼
       │    Download .hubpkg file
       │           │
       └───────────┘
       │
       ▼
Upload Package → Validation
       │               │
       │               ├─── Check dependencies
       │               ├─── Validate structure
       │               ├─── Verify compatibility
       │               ├─── Security scan
       │               ├─── Layer 2 compliance check:
       │               │     • If hub_cards exists → require workflow_states
       │               │     • If hub_cards exists → require manager_actions
       │               │     • If hub_cards exists → require audit_events
       │               │     • Validate state transition logic
       │               │     • Verify editable_fields have validation rules
       │               │     • Check audit event required_fields completeness
       │               └─── Database schema validation
       │
       ▼
Review Validation Report
       │
       ├─── Errors → Fix and re-upload
       │
       └─── Pass → Click Install
                      │
                      ▼
              Package Installation:
               - Create database tables
               - Insert default data
               - Create section entry
               - Grant role access
               - Install menu items
                      │
                      ▼
              Configuration:
               - Set section access (roles)
               - Configure package settings
               - Test functionality
                      │
                      ▼
              Activate Section
                      │
                      ▼
              Users see new module on hub.php
```

---

## Package Ecosystem

### Package Specification (V2)

Packages use a JSON-based `.hubpkg` format with comprehensive metadata. The specification supports **3-layer operational architecture** with explicit manager oversight.

#### Quick Reference: Package Specification Blocks

| Specification Block | Layer | Purpose | Required? |
|---------------------|-------|---------|-----------|
| **Required Fields** | All | Package identity, versioning, compatibility | ✅ Always |
| **hub_cards** | Layer 1 | User-facing submission entry points | ✅ For user-facing packages |
| **management_sections** | Layer 2 | Manager oversight/review interfaces | ✅ If hub_cards exists |
| **workflow_states** | Layer 2 | Submission lifecycle (SUBMITTED→APPROVED) | ✅ If hub_cards exists |
| **manager_actions** | Layer 2 | Edit boundaries, field-level permissions | ✅ If hub_cards exists |
| **audit_events** | Layer 2 | Required logging taxonomy | ✅ If hub_cards exists |
| **database** | All | Schema provisioning | ✅ For packages with data storage |
| **permissions** | All | Role-to-capability mapping | ✅ Always |

**Validation Rule:** Packages with `hub_cards` (Layer 1 submission entry points) **MUST** include `workflow_states`, `manager_actions`, and `audit_events` to support the Management Layer (Layer 2).

---

#### Required Fields:
```json
{
  "package_id": "unique-identifier",
  "display_name": "Human Readable Name",
  "version": "1.0.0",
  "author": "Author Name",
  "description": "Package purpose",
  "category": "operations|finance|student|reporting|etc",
  "hub_version_min": "1.0.0",
  "hub_version_max": null,
  "dependencies": [],
  "conflicts": []
}
```

#### Hub Cards (User-Facing Features):
```json
"hub_cards": [
  {
    "id": "fleet-management",
    "title": "Fleet Management",
    "description": "View fleet roster",
    "icon": "bi-truck",
    "access": ["hub_user", "management_admin"],
    "modules": ["view-fleet-roster"]
  }
]
```

#### Management Sections (Admin Tools):
```json
"management_sections": [
  {
    "id": "fleet-configuration",
    "title": "Fleet Configuration",
    "description": "Manage vehicles",
    "icon": "bi-gear-fill",
    "access": ["management_director", "management_admin"],
    "modules": ["manage-vehicles", "vehicle-assignments"]
  }
]
```

#### Database Schema:
```json
"database": {
  "schema_file": "database/schema.sql",
  "tables": [
    "vm_vehicles",
    "vm_fuel_logs",
    "vm_maintenance_events"
  ]
}
```

#### Permissions (Role Mapping):
```json
"permissions": {
  "hub_user": {
    "description": "Standard users",
    "capabilities": ["view_fleet", "submit_fuel_log"]
  },
  "management_admin": {
    "description": "Full access",
    "capabilities": ["*"]
  }
}
```

---

### Management Layer Specification (Layer 2 Support)

To formally support **manager oversight, edit authority, and audit-grade workflows**, packages must define three additional blocks:

#### 1. Workflow States (Submission Lifecycle)
Defines the lifecycle states a record can have and which transitions are allowed:

```json
"workflow_states": {
  "states": [
    {
      "id": "DRAFT",
      "label": "Draft",
      "description": "User is still editing",
      "color": "#6c757d",
      "terminal": false
    },
    {
      "id": "SUBMITTED",
      "label": "Submitted",
      "description": "Pending manager review",
      "color": "#0d6efd",
      "terminal": false
    },
    {
      "id": "IN_REVIEW",
      "label": "In Review",
      "description": "Manager actively reviewing",
      "color": "#ffc107",
      "terminal": false
    },
    {
      "id": "CORRECTED",
      "label": "Corrected",
      "description": "Manager corrected errors",
      "color": "#fd7e14",
      "terminal": false
    },
    {
      "id": "APPROVED",
      "label": "Approved",
      "description": "Manager approved submission",
      "color": "#198754",
      "terminal": true
    },
    {
      "id": "REJECTED",
      "label": "Rejected",
      "description": "Manager rejected submission",
      "color": "#dc3545",
      "terminal": true
    }
  ],
  "transitions": [
    { "from": "DRAFT", "to": "SUBMITTED", "actor": "submitter" },
    { "from": "SUBMITTED", "to": "IN_REVIEW", "actor": "manager" },
    { "from": "IN_REVIEW", "to": "CORRECTED", "actor": "manager" },
    { "from": "IN_REVIEW", "to": "APPROVED", "actor": "manager" },
    { "from": "IN_REVIEW", "to": "REJECTED", "actor": "manager" },
    { "from": "CORRECTED", "to": "APPROVED", "actor": "manager" }
  ],
  "default_state": "DRAFT"
}
```

**Purpose:** Makes submission lifecycle explicit and enforceable. Platform can validate state transitions and prevent invalid operations.

#### 2. Manager Actions (Edit Boundaries)
Defines **what managers can edit, when they can edit it, and what restrictions apply**:

```json
"manager_actions": {
  "editable_fields": [
    {
      "field": "fuel_gallons",
      "label": "Fuel Gallons",
      "allowed_states": ["SUBMITTED", "IN_REVIEW", "CORRECTED"],
      "requires_reason": true,
      "validation": {
        "type": "number",
        "min": 0.1,
        "max": 150
      }
    },
    {
      "field": "odometer_reading",
      "label": "Odometer Reading",
      "allowed_states": ["SUBMITTED", "IN_REVIEW"],
      "requires_reason": true,
      "validation": {
        "type": "number",
        "min": 0
      }
    },
    {
      "field": "trip_purpose_id",
      "label": "Trip Purpose",
      "allowed_states": ["SUBMITTED", "IN_REVIEW", "CORRECTED"],
      "requires_reason": false
    }
  ],
  "immutable_fields": [
    "created_at",
    "created_by_user_id",
    "vehicle_id",
    "original_submission_data"
  ],
  "capabilities_by_role": {
    "management_crew": {
      "can_edit": true,
      "can_approve": false,
      "can_reject": false,
      "can_delete": false,
      "can_export": true
    },
    "management_director": {
      "can_edit": true,
      "can_approve": true,
      "can_reject": true,
      "can_delete": false,
      "can_export": true
    },
    "management_admin": {
      "can_edit": true,
      "can_approve": true,
      "can_reject": true,
      "can_delete": true,
      "can_export": true
    }
  }
}
```

**Purpose:** Creates an **explicit edit authority boundary** that administrators can review and auditors can verify. Makes "manager can correct errors" a first-class, enforceable concept.

#### 3. Audit Events (Logging Taxonomy)
Defines **what events must be logged and what data must be captured**:

```json
"audit_events": {
  "events": [
    {
      "event_type": "FUEL_LOG_SUBMITTED",
      "description": "User submitted a fuel log",
      "severity": "info",
      "required_fields": ["user_id", "vehicle_id", "fuel_gallons", "odometer"]
    },
    {
      "event_type": "FUEL_LOG_REVIEWED",
      "description": "Manager marked fuel log as reviewed",
      "severity": "info",
      "required_fields": ["manager_id", "fuel_log_id", "review_notes"]
    },
    {
      "event_type": "FUEL_LOG_CORRECTED",
      "description": "Manager corrected fuel log data",
      "severity": "warning",
      "required_fields": [
        "manager_id",
        "fuel_log_id",
        "field_name",
        "old_value",
        "new_value",
        "correction_reason"
      ]
    },
    {
      "event_type": "FUEL_LOG_APPROVED",
      "description": "Manager approved fuel log",
      "severity": "info",
      "required_fields": ["manager_id", "fuel_log_id"]
    },
    {
      "event_type": "FUEL_LOG_REJECTED",
      "description": "Manager rejected fuel log",
      "severity": "warning",
      "required_fields": ["manager_id", "fuel_log_id", "rejection_reason"]
    }
  ],
  "audit_requirements": {
    "retention_days": null,
    "immutable": true,
    "log_table": "vm_audit_logs",
    "global_log": true
  }
}
```

**Purpose:** Standardizes audit logging across packages. Platform can validate that required events are logged and required fields are captured. Provides **audit-grade traceability** for manager actions.

---

#### Complete Package Example (Vehicle Maintenance)

```json
{
  "package_id": "vehicle-maintenance",
  "display_name": "Vehicle Maintenance & Fleet Tracking",
  "version": "1.0.0",
  "author": "Woodson ISD IT",
  "description": "Fleet management with fuel logging, maintenance tracking, and manager oversight",
  "category": "operations",
  "hub_version_min": "1.0.0",
  
  "hub_cards": [
    {
      "id": "submit-fuel-log",
      "title": "Fuel Log",
      "description": "Record fuel purchase",
      "icon": "bi-fuel-pump",
      "access": ["hub_user", "management_crew"],
      "modules": ["fuel-entry-form"]
    }
  ],
  
  "management_sections": [
    {
      "id": "fuel-management",
      "title": "Fuel Log Review",
      "description": "Review and correct fuel logs",
      "icon": "bi-clipboard-check",
      "access": ["management_crew", "management_director", "management_admin"],
      "modules": ["fuel-log-review", "fuel-log-reports"]
    }
  ],
  
  "workflow_states": {
    "states": [
      { "id": "SUBMITTED", "label": "Submitted", "color": "#0d6efd", "terminal": false },
      { "id": "IN_REVIEW", "label": "In Review", "color": "#ffc107", "terminal": false },
      { "id": "CORRECTED", "label": "Corrected", "color": "#fd7e14", "terminal": false },
      { "id": "APPROVED", "label": "Approved", "color": "#198754", "terminal": true }
    ],
    "transitions": [
      { "from": "SUBMITTED", "to": "IN_REVIEW", "actor": "manager" },
      { "from": "IN_REVIEW", "to": "CORRECTED", "actor": "manager" },
      { "from": "IN_REVIEW", "to": "APPROVED", "actor": "manager" },
      { "from": "CORRECTED", "to": "APPROVED", "actor": "manager" }
    ],
    "default_state": "SUBMITTED"
  },
  
  "manager_actions": {
    "editable_fields": [
      {
        "field": "fuel_gallons",
        "label": "Fuel Gallons",
        "allowed_states": ["SUBMITTED", "IN_REVIEW", "CORRECTED"],
        "requires_reason": true
      },
      {
        "field": "trip_purpose_id",
        "label": "Trip Purpose",
        "allowed_states": ["SUBMITTED", "IN_REVIEW"],
        "requires_reason": false
      }
    ],
    "immutable_fields": ["created_by_user_id", "vehicle_id", "created_at"],
    "capabilities_by_role": {
      "management_crew": { "can_edit": true, "can_approve": false },
      "management_director": { "can_edit": true, "can_approve": true }
    }
  },
  
  "audit_events": {
    "events": [
      {
        "event_type": "FUEL_LOG_SUBMITTED",
        "description": "User submitted fuel log",
        "severity": "info",
        "required_fields": ["user_id", "vehicle_id", "fuel_gallons"]
      },
      {
        "event_type": "FUEL_LOG_CORRECTED",
        "description": "Manager corrected fuel log",
        "severity": "warning",
        "required_fields": ["manager_id", "field_name", "old_value", "new_value", "reason"]
      }
    ]
  },
  
  "database": {
    "schema_file": "database/schema.sql",
    "tables": ["vm_fuel_logs", "vm_vehicles", "vm_audit_logs"]
  },
  
  "permissions": {
    "hub_user": {
      "description": "Can submit fuel logs",
      "capabilities": ["submit_fuel_log", "view_own_logs"]
    },
    "management_crew": {
      "description": "Can review and edit fuel logs",
      "capabilities": ["view_all_logs", "edit_fuel_logs", "run_reports"]
    },
    "management_director": {
      "description": "Full oversight authority",
      "capabilities": ["*"]
    }
  }
}
```

**Key Benefits of This Specification:**
1. ✅ **Explicit Manager Authority** - `manager_actions` makes edit boundaries auditor-visible
2. ✅ **Enforceable Workflows** - Platform can validate state transitions
3. ✅ **Guaranteed Auditability** - Required audit events ensure compliance
4. ✅ **Layer Separation** - Clear distinction between Layer 1 (submit) and Layer 2 (review/edit)
5. ✅ **Validation Ready** - Installation can check completeness before deploying

---

### Package Categories

| Category | Purpose | Example Packages |
|----------|---------|------------------|
| **Operations** | Fleet, facilities, maintenance | Vehicle Maintenance, Work Orders |
| **Finance** | Budgeting, reimbursements | Reimbursement Requests, Travel Claims |
| **Student** | Student-facing tools | Bullying Reports, Absence Requests |
| **Reporting** | Incident reporting, compliance | Incident Reports, Safety Audits |
| **Forms** | Custom data collection | Survey Builder, Permission Slips |
| **Workflows** | Approval processes | Multi-Step Approvals, Task Management |
| **Analytics** | Dashboards, data visualization | Spend Analytics, Fleet Reports |

---

### Layer 2 Compliance Matrix

Defines which packages **require** vs **optionally support** the Management Layer specification:

| Package Type | `workflow_states` | `manager_actions` | `audit_events` | Rationale |
|--------------|-------------------|-------------------|----------------|-----------|
| **User Submissions** (fuel logs, incident reports, reimbursements) | **Required** | **Required** | **Required** | Users submit data that managers must review/correct |
| **Management Tools** (fleet roster, vehicle assignments) | Optional | Optional | Recommended | Managers create/edit directly (not reviewing submissions) |
| **Reporting Only** (dashboards, analytics) | Not Required | Not Required | Not Required | Read-only data visualization |
| **Forms/Workflows** (approval processes) | **Required** | **Required** | **Required** | Explicit approval workflows with state transitions |
| **Configuration Tools** (settings, lookups) | Not Required | Not Required | Recommended | Admin-only configuration changes |

**Validation Rule:** If a package defines `hub_cards` (Layer 1 submission entry points), it **MUST** define all three Management Layer blocks (`workflow_states`, `manager_actions`, `audit_events`).

---

### Package Lifecycle

```
Development
    │
    ▼
Local Testing (.hubpkg created)
    │
    ▼
Submit to Package Repository (PR on GitHub)
    │
    ▼
Review Process:
  - Security scan
  - Code quality check
  - Documentation review
  - Functionality testing
    │
    ▼
Approval & Merge to main branch
    │
    ▼
Available in Package Repository
    │
    ▼
Admins discover & download from repo
    │
    ▼
Install in TheHub instance
    │
    ▼
Configure & activate
    │
    ▼
Users access via hub.php
    │
    ▼
Updates? → New version → Repeat cycle
```

---

## Security & Compliance

### Authentication Security
- **OAuth 2.0 Only** (no local passwords in production)
- **Domain Restrictions** (`@woodsonisd.net` email required)
- **Session Security** (PHP sessions with HTTP-only cookies)
- **CSRF Protection** (tokens on all POST/PUT/DELETE)
- **SSL/TLS Required** (HTTPS enforced)
Manager Edit Authority Tracking:**
  - When a manager edits a submitted record:
    - Original state logged
    - Modified state logged
    - Actor (manager) recorded
    - Timestamp recorded
    - Reason for change (if provided)
  - **Control Mechanism:** Managers can correct operational errors with full transparency
  - **Accountability:** Every edit is traceable and reversible
- **Database Tables**: `audit_logs` (core), package-specific logs
- **Retention**: Indefinite (compliance requirement)
- **Review Interface**: Admin Dashboard → Activity Logs (filterable by actor, action, date)ssions
- **Section-Level Access** - Users only see authorized modules
- **Module-Level Permissions** - Read vs. write vs. admin
- **Super Admin "View As"** - Impersonation for support
- **Invitation System** - Controlled user onboarding

### Audit Logging
- **All Mutations Logged** (`AuditLogger` class)
- **Who, What, When, Before/After** - Complete audit trail
- **Database Tables**: `audit_logs` (core), package-specific logs
- **Retention**: Indefinite (compliance requirement)
- **Review Interface**: Admin Dashboard → Activity Logs

### Package Validation
Before installation, packages pass **10+ security checks**:
1. ✅ Structure validation (required fields present)
2. ✅ SQL injection detection (prepared statements enforced)
3. ✅ XSS prevention (input validation rules)
4. ✅ File upload restrictions (size, type validation)
5. ✅ Dependency resolution (no circular dependencies)
6. ✅ Conflict detection (namespace collisions)
7. ✅ Version compatibility (Hub version requirements)
8. ✅ Permission analysis (valid role mappings)
9. ✅ Database impact (schema changes reviewed)
10. ✅ Performance checks (query complexity limits)

### Data Protection
- **Soft Deletes** (`is_active` flag, no hard deletes)
- **Database Backups** (weekly automated)
- **Session Expiration** (configurable timeout)
- **Password Hashing** (bcrypt, cost factor 12)
- **Sensitive Data Masking** (PHI/PII redaction in logs)

---

## Technical Infrastructure

### Technology Stack

#### Backend
- **PHP 8.3+** (PSR-4 autoloading, type declarations)
- **Composer** (dependency management)
- **MySQL 8.0+** (InnoDB, foreign key constraints)

#### Frontend
- **HTML5** (semantic markup)
- **CSS3** (CSS variables, responsive design)
- **Vanilla JavaScript** (no frameworks, ES6+)
- **Bootstrap Icons** (simple icons)
- **FontAwesome 6** (icon library)

#### Authentication
- **Google OAuth 2.0** (primary)
- **Microsoft Azure AD** (secondary)
- **Google API Client** (PHP library)
- **Google Groups API** (auto-approval)

#### Libraries
- **PHPMailer** (email notifications)
- **PhpSpreadsheet** (Excel/CSV export)
- **TCPDF** (PDF generation)

### Development Workflow

#### Git Workflow
- **Main Branch**: `v1.1` (stable, production-ready)
- **Dev Branch**: `laravel-migration` (active development)
- **Feature Branches**: Short-lived, merged via PR
- **Git Hooks**: Pre-commit safety checks, auto-snapshots

#### Testing
- **PHPUnit** (unit and integration tests)
- **Test Database**: `woodson_hub` (currently using production DB for dev)
- **Coverage Target**: 60-65% overall, 70% auth coverage
- **Current Coverage**: 44.38% overall (improving)

#### Deployment
- **Server**: Ubuntu Server (Apache/Nginx)
- **Environment**: Production config in `.env`
- **Migrations**: CLI scripts (`php cli/migrate.php`)
- **Zero-Downtime**: Maintenance mode toggle

### Database Architecture

#### Core Tables (TheHub Repository)
```sql
users                    -- User accounts
global_roles             -- System-wide roles
sections                 -- Installed modules/packages
section_role_access      -- Who can access which sections
section_installations    -- Package installation history
section_packages         -- Uploaded packages (pre-install)
audit_logs               -- Compliance audit trail
site_settings            -- System configuration
invitations              -- User invitation tokens
modules                  -- Legacy module system (deprecated)
```

#### Package Tables (Varies by Package)
Each package creates its own namespaced tables:
```sql
vm_vehicles              -- Vehicle Maintenance package
vm_fuel_logs
reimb_monetary_request   -- Reimbursement package
reimb_fuel_trip
br_incidents             -- Bullying Report package
br_followups
```

### API Design

#### RESTful Endpoints
```
GET    /api/users.php?action=list       -- List users
POST   /api/users.php?action=create     -- Create user
PUT    /api/users.php?action=update     -- Update user
DELETE /api/users.php?action=delete     -- Soft-delete user

GET    /api/packages.php?action=list    -- List packages
POST   /api/packages.php?action=upload  -- Upload package
POST   /api/packages.php?action=install -- Install package
```

#### Response Format
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation completed successfully",
  "errors": []
}
```

---

## Future Roadmap

### Phase 1: Stabilization (Q1 2026) ✅
- [x] Core platform architecture
- [x] Package management system
- [x] OAuth authentication (Google + Microsoft)
- [x] Admin dashboard
- [x] Initial packages (Vehicle Maintenance, Bullying Report)

### Phase 2: Testing & Refinement (Q2 2026) 🔄
- [ ] Test coverage >60%
- [ ] Security audit completion
- [ ] Performance optimization
- [ ] Mobile responsiveness improvements
- [ ] Package repository GitHub launch

### Phase 3: Production Deployment (Q3 2026)
- [ ] Separate test database configuration
- [ ] Production server setup (HTTPS, backups)
- [ ] User training materials
- [ ] Data migration from legacy systems
- [ ] Soft launch with pilot users

### Phase 4: Expansion (Q4 2026)
- [ Hub Layer** | Layer 1 - End user data submission interface |
| **Management Layer** | Layer 2 - Operational oversight with edit authority |
| **Administrator Layer** | Layer 3 - Platform governance and system configuration |
| **Module** | Self-contained functional unit (package) |
| **Package** | `.hubpkg` file containing module code and metadata |
| **Section** | Database representation of an installed package |
| **Hub Card** | User-facing module feature shown on hub.php |
| **Management Section** | Operational oversight interface for managers |
| **Admin Dashboard** | Platform governance interface for system configuration |
| **Manager Role** | Layer 2 role with view/edit authority over submissions (no system config access) |
| **Role** | Permission level defining layer access (staff, manager, admin, super_admin, etc.) |
| **Section Access** | Permission to see a specific module |
| **Edit Authority** | Permission to modify submitted records (tracked in audit log) |
| **Super Admin** | Highest privilege level with "View As" capability |
| **Operational Oversight** | Layer 2 function - reviewing and correcting business process data |
| **Platform Governance** | Layer 3 function - configuring system settings and permissions |
| **Audit Trail** | Immutable log of all mutations (who, what, when, before, after)
- **White-Label Branding** - Customizable for each district
- **Marketplace** - Commercial packages from vendors
- **Enterprise SaaS** - Cloud-hosted option
- **Open-Source Core** - Community-driven development

---

## Appendix A: Key Terminology

| Term | Definition |
|------|------------|
| **Hub** | Landing page showing available modules (hub.php) |
| **Hub Layer** | Layer 1 - End user data submission interface |
| **Management Layer** | Layer 2 - Operational oversight with edit authority |  
| **Administrator Layer** | Layer 3 - Platform governance and system configuration |
| **Module** | Self-contained functional unit (package) |
| **Package** | `.hubpkg` file containing module code and metadata |
| **Section** | Database representation of an installed package |
| **Hub Card** | User-facing module feature shown on hub.php |
| **Management Section** | Operational oversight interface for managers |
| **Workflow State** | Lifecycle stage of a submission (SUBMITTED, IN_REVIEW, APPROVED, etc.) |
| **Manager Actions** | Defined edit boundaries for operational oversight roles |
| **Audit Events** | Standardized log entries for package-specific mutations |
| **Edit Authority** | Permission to modify submitted records (tracked in audit log) |
| **Immutable Field** | Data field that cannot be changed after creation |
| **State Transition** | Movement from one workflow state to another |
| **Terminal State** | Final state that cannot transition further (APPROVED, REJECTED) |
| **Role** | Permission level (staff, manager, admin, super_admin, etc.) |
| **Section Access** | Permission to see a specific module |
| **Super Admin** | Highest privilege level with "View As" capability |
| **CSRF** | Cross-Site Request Forgery (security token) |
| **OAuth** | Open Authorization standard (Google/Microsoft login) |

---

## Appendix B: Implementation Guidelines for Package Developers

### Implementing Layer 2 Workflows in Your Package

#### 1. Database Schema Requirements

Your package must define a `status` column to track workflow states:

```sql
CREATE TABLE vm_fuel_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  
  -- Submission data
  user_id INT NOT NULL,
  vehicle_id INT NOT NULL,
  fuel_gallons DECIMAL(6,2) NOT NULL,
  odometer INT NOT NULL,
  
  -- Workflow tracking
  status ENUM('DRAFT','SUBMITTED','IN_REVIEW','CORRECTED','APPROVED','REJECTED') 
    DEFAULT 'DRAFT' NOT NULL,
  status_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  status_changed_by INT NULL,
  
  -- Audit fields
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_by_user_id INT NOT NULL,  -- Original submitter (IMMUTABLE)
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by_user_id INT NULL,      -- Last editor (manager or user)
  
  -- Optional workflow fields
  reviewed_by_manager_id INT NULL,
  review_notes TEXT NULL,
  correction_reason TEXT NULL,
  
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (created_by_user_id) REFERENCES users(id),
  INDEX idx_status (status),
  INDEX idx_created_by (created_by_user_id)
);
```

**Critical Requirements:**
- ✅ `status` column with ENUM matching your `workflow_states.states[].id`
- ✅ `created_by_user_id` marked IMMUTABLE (never changes, preserves original submitter)
- ✅ `updated_by_user_id` tracks last editor (can be manager)
- ✅ Timestamps for both creation and updates
- ✅ Optional fields for manager review notes/reasons

#### 2. Enforcing Manager Actions in PHP

```php
<?php
// File: /pkg/vm/controllers/FuelLogController.php

class FuelLogController {
    
    public function updateRecord(int $logId, array $updates, int $managerId): bool {
        // 1. Load current record and package spec
        $log = $this->db->query("SELECT * FROM vm_fuel_logs WHERE id = ?", [$logId])->fetch();
        $spec = json_decode(file_get_contents(__DIR__ . '/../package.json'), true);
        
        // 2. Validate current state allows editing
        $managerActions = $spec['manager_actions'];
        
        foreach ($updates as $field => $newValue) {
            // Check if field is immutable
            if (in_array($field, $managerActions['immutable_fields'])) {
                throw new Exception("Field '{$field}' cannot be modified");
            }
            
            // Check if field is editable in current state
            $fieldConfig = $this->getFieldConfig($managerActions, $field);
            if (!in_array($log['status'], $fieldConfig['allowed_states'])) {
                throw new Exception("Field '{$field}' cannot be edited in state '{$log['status']}'");
            }
            
            // If reason required, ensure it's provided
            if ($fieldConfig['requires_reason'] && empty($updates['_correction_reason'])) {
                throw new Exception("Correction reason required for field '{$field}'");
            }
        }
        
        // 3. Log the edit event BEFORE making changes
        $this->auditLog([
            'event_type' => 'FUEL_LOG_CORRECTED',
            'severity' => 'warning',
            'manager_id' => $managerId,
            'fuel_log_id' => $logId,
            'field_name' => implode(',', array_keys($updates)),
            'old_value' => json_encode(array_intersect_key($log, $updates)),
            'new_value' => json_encode($updates),
            'correction_reason' => $updates['_correction_reason'] ?? null
        ]);
        
        // 4. Perform the update
        $this->db->update('vm_fuel_logs', $updates, ['id' => $logId]);
        
        // 5. Update workflow tracking
        $this->db->update('vm_fuel_logs', [
            'status' => 'CORRECTED',
            'updated_by_user_id' => $managerId,
            'correction_reason' => $updates['_correction_reason'] ?? null
        ], ['id' => $logId]);
        
        return true;
    }
    
    private function getFieldConfig($managerActions, $field) {
        foreach ($managerActions['editable_fields'] as $config) {
            if ($config['field'] === $field) {
                return $config;
            }
        }
        throw new Exception("Field '{$field}' not defined in manager_actions");
    }
    
    private function auditLog(array $event) {
        // Log to both package-specific and global audit tables
        $this->db->insert('vm_audit_logs', $event);
        AuditLogger::log('package.vehicle_maintenance', $event['event_type'], $event);
    }
}
```

#### 3. Validating State Transitions

```php
<?php
// File: /pkg/vm/helpers/WorkflowValidator.php

class WorkflowValidator {
    
    public static function canTransition(string $currentState, string $targetState, string $actorRole): bool {
        $spec = json_decode(file_get_contents(__DIR__ . '/../package.json'), true);
        $transitions = $spec['workflow_states']['transitions'];
        
        foreach ($transitions as $transition) {
            if ($transition['from'] === $currentState && 
                $transition['to'] === $targetState &&
                self::actorMatches($transition['actor'], $actorRole)) {
                return true;
            }
        }
        
        return false;
    }
    
    private static function actorMatches(string $requiredActor, string $actualRole): bool {
        $actorMap = [
            'submitter' => ['hub_user'],
            'manager' => ['management_crew', 'management_director', 'management_admin'],
            'admin' => ['admin', 'super_admin']
        ];
        
        return in_array($actualRole, $actorMap[$requiredActor] ?? []);
    }
    
    public static function isTerminalState(string $state): bool {
        $spec = json_decode(file_get_contents(__DIR__ . '/../package.json'), true);
        
        foreach ($spec['workflow_states']['states'] as $stateConfig) {
            if ($stateConfig['id'] === $state) {
                return $stateConfig['terminal'] ?? false;
            }
        }
        
        return false;
    }
}
```

#### 4. Frontend UI Example (Manager Review Interface)

```php
<!-- File: /pkg/vm/views/manager/fuel-log-review.php -->

<?php
$log = $fuelLog; // Passed from controller
$canEdit = in_array($log['status'], ['SUBMITTED', 'IN_REVIEW', 'CORRECTED']);
$isTerminal = in_array($log['status'], ['APPROVED', 'REJECTED']);
?>

<div class="fuel-log-review">
    <div class="status-badge status-<?= strtolower($log['status']) ?>">
        <?= $log['status'] ?>
    </div>
    
    <table class="table">
        <tr>
            <td>Original Submitter:</td>
            <td><strong><?= htmlspecialchars($log['submitter_name']) ?></strong></td>
        </tr>
        <tr>
            <td>Submitted:</td>
            <td><?= date('M d, Y g:i A', strtotime($log['created_at'])) ?></td>
        </tr>
        
        <!-- Editable Fields -->
        <tr>
            <td>Fuel Gallons:</td>
            <td>
                <?php if ($canEdit): ?>
                    <input type="number" name="fuel_gallons" value="<?= $log['fuel_gallons'] ?>" 
                           min="0.1" max="150" step="0.01" class="form-control">
                    <small class="text-muted">Manager can correct if error found</small>
                <?php else: ?>
                    <?= $log['fuel_gallons'] ?> gal
                    <?php if ($isTerminal): ?>
                        <span class="badge bg-secondary">Locked</span>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        
        <!-- Immutable Fields -->
        <tr>
            <td>Vehicle:</td>
            <td>
                <?= htmlspecialchars($log['vehicle_name']) ?>
                <span class="badge bg-info">Immutable</span>
            </td>
        </tr>
    </table>
    
    <?php if ($canEdit): ?>
        <div class="correction-reason">
            <label>Reason for Changes (required if editing):</label>
            <textarea name="correction_reason" class="form-control" rows="3"></textarea>
        </div>
        
        <div class="actions mt-3">
            <button class="btn btn-warning" onclick="saveCorrections()">
                Save Corrections
            </button>
            <button class="btn btn-success" onclick="approve()">
                Approve
            </button>
            <button class="btn btn-danger" onclick="reject()">
                Reject
            </button>
        </div>
    <?php endif; ?>
</div>
```

**Key Takeaways for Developers:**
1. ✅ Always validate against `manager_actions.editable_fields` before allowing edits
2. ✅ Preserve `created_by_user_id` - NEVER modify original submitter
3. ✅ Log every edit with before/after values via `audit_events`
4. ✅ Enforce state transitions via `workflow_states`
5. ✅ Require correction reasons when `requires_reason: true`
6. ✅ Lock terminal states (APPROVED/REJECTED) from further changes

---

## Appendix C: GitHub Repository URLs

### TheHub (Core Platform)
- **Repository**: https://github.com/R1CH4RD25/TheHub
- **Active Branch**: `laravel-migration`
- **Default Branch**: `v1.1`
- **Clone URL**: `git clone https://github.com/R1CH4RD25/TheHub.git`

### TheHub-Package-Repo (Package Ecosystem)
- **Repository**: https://github.com/R1CH4RD25/TheHub-Package-Repo
- **License**: MIT
- **Browse Packages**: https://github.com/R1CH4RD25/TheHub-Package-Repo/tree/main/packages
- **Contributing**: https://github.com/R1CH4RD25/TheHub-Package-Repo/blob/main/CONTRIBUTING.md

---

## Appendix D: Contact Information

**Woodson ISD Technology Department**
**Project Lead:** Richard Sullivan
**Email:** richard.sullivan@woodsonisd.net
**Project Website:** https://hub.woodsonisd.net (in development)

**For External Auditors:**
Technical questions, security documentation, or system access requests should be directed to the project lead.

---

**Document End**
*This document is maintained in: `/var/www/woodson/thehub/THE_HUB_CONCEPT_AND_ARCHITECTURE.md`*
