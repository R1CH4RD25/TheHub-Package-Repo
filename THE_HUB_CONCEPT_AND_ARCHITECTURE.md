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

#### 3. **Role-Based Access Control**
Granular permissions at three levels:
- **Global Roles:** Admin, Super Admin, Staff, Student
- **Section Access:** Which modules a user can see
- **Module Permissions:** What actions they can perform within each module

#### 4. **Hub & Management Separation**
- **Hub Interface:** User-facing tools for daily tasks (submit forms, view data)
- **Management Console:** Administrative configuration, reporting, analytics

---

## System Architecture

### Three-Layer Design

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
- Global roles (super_admin, admin, staff, student, etc.)
- Section-level access control
- Module-specific permissions
- "View As" capability for super admins

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
   - Fuel logging with trip categorization
   - Maintenance scheduling and tracking
   - Fleet roster management
   
2. **Bullying Report System** `v1.0.0`
   - Anonymous incident reporting
   - Admin review and tracking
   
3. **Reimbursement Request & Fuel Tracking** `v1.0.0`
   - Monetary reimbursement workflows
   - Fuel trip tracking with approval process

#### Responsibilities:
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

### 2. Module Access Flow
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
                    Open module interface
                            │
                            ├─── Hub Interface (user tasks)
                            └─── Management (admin tools)
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
       │               └─── Security scan
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

Packages use a JSON-based `.hubpkg` format with comprehensive metadata:

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

### Authorization Controls
- **Role-Based Access Control (RBAC)** - Granular permissions
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
- [ ] Additional packages (Work Orders, Asset Tracking)
- [ ] API for third-party integrations
- [ ] Mobile app companion (future consideration)
- [ ] Advanced analytics and reporting
- [ ] Community package contributions

### Long-Term Vision
- **Multi-Tenant Support** - Other districts can use The Hub
- **White-Label Branding** - Customizable for each district
- **Marketplace** - Commercial packages from vendors
- **Enterprise SaaS** - Cloud-hosted option
- **Open-Source Core** - Community-driven development

---

## Appendix A: Key Terminology

| Term | Definition |
|------|------------|
| **Hub** | Landing page showing available modules (hub.php) |
| **Module** | Self-contained functional unit (package) |
| **Package** | `.hubpkg` file containing module code and metadata |
| **Section** | Database representation of an installed package |
| **Hub Card** | User-facing module feature shown on hub.php |
| **Management Section** | Admin-facing configuration interface |
| **Role** | Permission level (staff, admin, super_admin, etc.) |
| **Section Access** | Permission to see a specific module |
| **Super Admin** | Highest privilege level with "View As" capability |
| **CSRF** | Cross-Site Request Forgery (security token) |
| **OAuth** | Open Authorization standard (Google/Microsoft login) |

---

## Appendix B: GitHub Repository URLs

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

## Appendix C: Contact Information

**Woodson ISD Technology Department**  
**Project Lead:** Richard Sullivan  
**Email:** richard.sullivan@woodsonisd.net  
**Project Website:** https://hub.woodsonisd.net (in development)

**For External Auditors:**  
Technical questions, security documentation, or system access requests should be directed to the project lead.

---

**Document End**  
*This document is maintained in: `/var/www/woodson/thehub/THE_HUB_CONCEPT_AND_ARCHITECTURE.md`*
