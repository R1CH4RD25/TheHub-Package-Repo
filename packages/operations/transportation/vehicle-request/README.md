# Vehicle Request Form

Request, approve, and manage school vehicle usage with multi-vehicle assignments, availability checking, and trip analytics.

## Features

- **Request Submission** — Staff submit vehicle requests with trip details, passenger counts, and scheduling
- **Approval Workflow** — Administrators review and approve/deny requests with denial reasons
- **Vehicle Assignment** — Assign specific vehicles from the fleet to approved trips
- **Fleet Management** — Manage district vehicles with type, capacity, and availability tracking
- **Analytics** — Requests by category, monthly trends, and vehicle utilization charts
- **Export** — CSV and XLSX export for all request tables

## Roles

| Role | Access |
|------|--------|
| Requester | Submit requests, view own, cancel pending |
| Approver | Review/approve/deny, assign vehicles, view analytics |
| Fleet Admin | Manage vehicles, full approver access |
| Admin | Full transportation management |

## Database Tables

- `vrf_requests` — Vehicle requests with trip details and approval status
- `vrf_vehicles` — Fleet vehicle inventory
- `vrf_request_vehicles` — Many-to-many request↔vehicle assignments
- `vrf_audit_logs` — Audit trail for all actions

## Integration

If the **Vehicle Maintenance** package is installed, this package can optionally pull fleet inventory via the `operations.vehicles` resource contract.

## Installation

Install via TheHub Package Manager. Database tables and seed data are created automatically.

## Version History

See [CHANGELOG.md](CHANGELOG.md) for details.
