# Student-Facing Packages

Packages in this directory are designed for student use - tools that students interact with directly.

## Directory Structure

```
student/
├── safety/          - Student safety and incident reporting
├── forms/           - Student submission forms (future)
├── resources/       - Student resources and guides (future)
└── communication/   - Student-parent communication (future)
```

## Current Packages

### Safety (student/safety/)
- **bullying-report** - Anonymous bullying incident reporting

## Package Organization

Packages here follow multi-dimensional tagging:
- First tag: `student` (audience)
- Second tag: Category (e.g., `safety`, `forms`, `resources`)

**Example:**
- `student/safety/bullying-report` → Tags: `['student', 'safety']`
- `student/forms/absence-request` → Tags: `['student', 'forms']`

This allows filtering by:
- All student tools: Filter by `student`
- All safety tools: Filter by `safety`
- Student safety tools: Filter by both `student` AND `safety`
