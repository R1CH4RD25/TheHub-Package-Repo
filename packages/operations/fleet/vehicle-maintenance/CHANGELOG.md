# Changelog – Vehicle Maintenance & Fleet Tracking

## [2.1.0] – 2025-06-01

### Added
- Full Schema v3.0.0 rewrite for The Hub package system
- 11 JSON-driven pages: fleet roster, vehicle detail, fuel/maintenance forms, logs, schedules, config
- Layer 2 workflow: submitted → in_review → corrected → approved / rejected
- Edit boundaries with correction reasons and immutable field enforcement
- Dashboard KPI cards for fleet stats, fuel stats, maintenance costs
- Grid-based form layouts with validation rules
- Trip category management (codes 11, 23, 34, 36, 41)
- Maintenance templates with interval-based scheduling
- File upload support for receipts and invoices
- Full audit event trail (13 event types)
- Role system: user → manager → admin with globalRoleMapping
- Responsive tables with hide-mobile/hide-tablet column support

### Changed
- Migrated from Schema v1 manifest.json to Schema v3.0.0 package.json
- Package ID changed from `com.woodson.vehicle-maintenance` to `operations.vehicle-maintenance`

## [2.0.0] – 2025-03-15

### Added
- Initial manifest.json (Schema v1) with basic fleet and fuel tracking
- Database schema for woodson_fleet

## [1.0.0] – 2024-12-01

### Added
- Original concept and requirements gathering
