# Bullying Report

Confidential bullying incident reporting with counselor review workflow, investigation tracking, and resolution management.

## Features

- **Anonymous Reporting** — Students and staff can submit reports with or without identifying themselves
- **Counselor Dashboard** — Overview of all reports with status filters and statistics
- **Investigation Workflow** — Track reports through new → under review → investigating → resolved/dismissed
- **Assignment** — Assign reports to specific counselors or administrators
- **Staff Notes** — Internal notes and resolution tracking
- **Audit Trail** — Full audit log of all status changes and edits

## Roles

| Role | Access |
|------|--------|
| Reporter (user) | Submit bullying reports |
| Counselor | Review, investigate, resolve, assign reports |
| Admin | Full access to all reports and configuration |

## Database Tables

- `br_reports` — Incident reports with status, assignment, and resolution tracking
- `br_audit_logs` — Audit trail for all report actions

## Installation

Install via TheHub Package Manager. Database tables are created automatically during installation.

## Version History

See [CHANGELOG.md](CHANGELOG.md) for details.
