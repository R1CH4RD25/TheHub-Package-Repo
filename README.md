# The Hub Package Repository

Official package repository for **The Hub** platform. All packages use schema version 3.0.0 and are installed via TheHub Package Manager.

## 📦 Active Packages

| Package | Version | Category | Description |
|---------|---------|----------|-------------|
| [Vehicle Maintenance & Fleet Tracking](packages/operations/fleet/vehicle-maintenance/) | 2.1.0 | Operations | Fleet inventory, fuel/trip tracking, maintenance scheduling with Layer 2 workflows |
| [Student Directory](packages/district/student-directory/) | 1.0.0 | District | Student records management with import/export |
| [Bullying Report](packages/student/safety/bullying-report/) | 1.0.0 | Student Safety | Confidential incident reporting with counselor review workflow |
| [Reimbursement Request & Fuel Tracking](packages/finance/reimbursement-request/) | 1.0.0 | Finance | Monetary reimbursement + fuel trip tracking with approval workflow |
| [Vehicle Request Form](packages/operations/transportation/vehicle-request/) | 1.0.0 | Operations | Vehicle request, approval, and fleet assignment management |

## 🗓️ Planned Packages

| Package | Category | Notes |
|---------|----------|-------|
| Personal Vehicle / Fuel Reimbursement | Finance | IRS mileage rate reimbursement for personal vehicle use — **deferred, needed later** |

## 📂 Repository Structure

```
packages/
├── archive/                        # Retired package versions
├── district/
│   └── student-directory/          # v3 — Student records
├── finance/
│   └── reimbursement-request/      # v3 — Monetary + fuel reimbursement
├── operations/
│   ├── fleet/
│   │   └── vehicle-maintenance/    # v3 — Fleet tracking + maintenance
│   └── transportation/
│       └── vehicle-request/        # v3 — Vehicle request + approval
└── student/
    └── safety/
        └── bullying-report/        # v3 — Incident reporting
```

Each package directory contains:
- `package.json` — v3.0.0 schema definition (presentation, data, policy, access)
- `database/schema.sql` — Table definitions (CREATE IF NOT EXISTS)
- `database/seed.sql` — Default data (optional)
- `README.md` — Package documentation
- `CHANGELOG.md` — Version history
- `LICENSE` — MIT license

## 🔧 Schema Version 3.0.0

All packages follow the v3 schema with these top-level sections:

| Section | Purpose |
|---------|---------|
| `package` | ID, name, version, icon, category, base_url |
| `database` | Connection, primaryTable, auditTable |
| `resources` | Cross-package data contracts (provides/requires) |
| `presentation.pages` | Page definitions with components (dashboard, table, form, detail, analytics) |
| `data.queries` | Named queries with handler refs and parameters |
| `data.mutations` | Named mutations with handler refs and audit flags |
| `policy.roles` | Package-specific roles with query/mutation permissions and inheritance |
| `access.layer2` | Workflow states/transitions, edit boundaries, audit events |

## 🔗 Related

- **[TheHub](https://github.com/R1CH4RD25/TheHub)** — Backend/frontend platform code
- **[TheHub-Package-Repo](https://github.com/R1CH4RD25/TheHub-Package-Repo)** — This repository (packages only)

## ⚠️ Important

- Never commit `.hubpkg` files or `packages/` content to the main TheHub repo
- Push package changes with `git push packages <branch>` only
- All packages target `woodson_hub` database unless noted (Student Directory uses `woodson_students`)
