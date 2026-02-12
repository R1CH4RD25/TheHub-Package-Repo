# Changelog

All notable changes to the Student Directory package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Student photo management
- Batch password reset by grade/school
- Google Workspace API direct sync

---

## [1.0.0] - 2025-02-12

### Added

#### Core Features
- Student records search, view, add, edit, delete
- Full-text search by name, student ID, or email
- Grade-level (PK–12) and graduation year filtering
- Sortable, paginated student table (25/50/100/200 per page)
- Detailed student profile view with personal info, account credentials, demographics

#### Credential Generation
- Automatic Chromebook login generation (`firstname.lastname@woodsonisd.net`)
- Grade-based password algorithm (PK–4: `Firstname` + last 4 ID; 5–12: `Firstname` + last 4 ID + `!`)
- Automatic Google OU assignment (`Students/{Grade}/{Level}`)
- Grade-level Google group email assignment

#### Import/Export
- CSV import with three modes: Append, Rewrite, Ignore
- Flexible column mapping with header auto-detection
- Import preview before committing changes
- Import history tracking with status
- Standard CSV export (all fields)
- Google Workspace CSV export (Google Admin console format)
- Printable login cards (individual and bulk)

#### Package System (Layer 3)
- Full Layer 3 package manifest (schema v3.0.0)
- 6 declarative page definitions (index, view, add, edit, import, export)
- 6 data queries with caching support
- 5 mutations with audit logging
- Role-based access (viewer/editor/importer)
- Google Group auto-mapping for role assignment
- Unified `StudentDirectoryHandler` bridge class
- District package namespace support in `PackageLoader`
- Handler registration in package front controller

#### Testing
- 97 PHPUnit tests (all passing)
- Password generation algorithm tests (43)
- Query handler tests (14)
- Mutation handler tests (13)
- Export handler tests (7)
- Handler bridge tests (12)
- Package loader district tests (8)

### Compatibility
- Hub Version: >=1.0.0
- Schema Version: 3.0.0
- PHP: >=8.2
- MySQL: >=8.0

### Documentation
- Complete README with installation guide
- Architecture documentation (pages, queries, mutations)
- API reference (18 REST endpoints)
- Password algorithm specification
- Role & permissions matrix
- Database schema reference
- Roadmap (v1.1, v1.2, v2.0)
