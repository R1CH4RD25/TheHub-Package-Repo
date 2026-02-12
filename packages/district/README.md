# District Packages

Packages for district-wide administration, records management, and institutional data tools.

## Directory Structure

```
district/
├── student-directory/   - Student records management & Google Workspace integration
├── staff-directory/     - Staff records and HR tools (future)
└── enrollment/          - Student enrollment workflows (future)
```

## Current Packages

### 👨‍🎓 Student Directory v1.0.0
**Directory:** `student-directory/`

Comprehensive student records management with search, view, edit, import/export, and automatic Google Workspace credential generation. Connects to a dedicated `woodson_students` database.

**Features:**
- Full-text search with grade and graduation year filtering
- Automatic Chromebook login, password, Google OU generation
- CSV import (3 modes: append, rewrite, ignore) with preview
- Standard and Google Workspace CSV export
- Printable login cards (individual and bulk)
- Role-based access: viewer, editor, importer
- Google Group auto-mapping for permissions

**Tags:** `district`, `students`, `records`, `google-workspace`, `csv-import`

**For:** Technology staff (import/manage), Administrators (edit), Teachers/Office (view)

**Requirements:**
- Hub >=1.0.0
- PHP >=8.2
- MySQL >=8.0
- Separate `woodson_students` database

---

**Installation:**
1. Admin Dashboard → Package Manager → Upload Package
2. Select `student-directory_1.0.0.hubpkg`
3. Configure database connection to `woodson_students`
4. Assign section access in Admin → Sections

## Package Organization

Packages here follow multi-dimensional tagging:
- First tag: `district` (scope)
- Second tag: Category (e.g., `students`, `staff`, `enrollment`)

**Example:**
- `district/student-directory` → Tags: `['district', 'students', 'records', 'google-workspace']`
- `district/staff-directory` → Tags: `['district', 'staff', 'hr']`

This allows filtering by:
- All district tools: Filter by `district`
- All student tools: Filter by `students`
- District student tools: Filter by both `district` AND `students`

## Contributing

See [CONTRIBUTING.md](../../CONTRIBUTING.md) for guidelines on submitting district packages.
