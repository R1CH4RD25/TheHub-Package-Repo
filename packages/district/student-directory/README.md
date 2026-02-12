# Student Directory Package

**Version:** 1.0.0  
**Author:** Woodson ISD Technology Department  
**License:** Proprietary  
**Category:** District  
**Package ID:** `district.student-directory`  
**Schema Version:** 3.0.0

## Overview

The Student Directory package provides comprehensive student records management for district staff. It connects to a dedicated `woodson_students` database and offers search, view, edit, import/export capabilities with automatic Google Workspace credential generation.

Designed as a **Layer 3 package** for The Hub's modular architecture, it demonstrates the full package pipeline: declarative page definitions, query/mutation handlers, and role-based access control.

## Features

### 🔍 Student Search & Listing
- Full-text search by name, student ID, or email
- Filter by grade level (PK–12) and graduation year
- Sortable columns with configurable pagination (25/50/100/200 per page)
- Quick-view actions for each student record

### 👤 Student Detail View
- Complete student profile with personal info, account credentials, and demographics
- One-click password reveal (audited)
- Reset password to grade-based default
- Print individual login card
- Direct edit navigation

### ✏️ Add & Edit Students
- Sectioned form with required and optional fields
- Automatic credential generation:
  - **Chromebook Login:** `firstname.lastname@woodsonisd.net`
  - **Password:** Grade-based formula (PK–4: `Firstname` + last 4 of ID; 5–12: `Firstname####!`)
  - **Google OU:** `Students/{Grade}/{Level}`
  - **Group Email:** Grade-level Google group
- Demographics tracking (Hispanic/Latino, race categories)
- Duplicate student ID detection

### 📥 CSV Import
- Three import modes:
  - **Append** — Add new students only (skip existing)
  - **Rewrite** — Update existing, add new
  - **Ignore** — Skip existing students
- Flexible column mapping with header detection
- Import preview before committing
- Full import history with status tracking

### 📤 Export
- **Standard CSV** — All student fields for SIS integration
- **Google Workspace CSV** — Format compatible with Google Admin console bulk upload
- **Print Cards** — Bulk or individual printable login cards

### 🔐 Role-Based Access
| Role | Description | Capabilities |
|------|-------------|--------------|
| **Viewer** | Teachers, office staff | Search, view records, print cards |
| **Editor** | Administrators | Add, edit, delete students, reset passwords |
| **Importer** | Technology staff | CSV import/export, bulk operations |

### 🔗 Google Group Auto-Mapping
| Google Group | Package Role |
|---|---|
| `technology@woodsonisd.net` | Importer |
| `admin@woodsonisd.net` | Editor |
| `teachers@woodsonisd.net` | Viewer |
| `office@woodsonisd.net` | Viewer |

## Requirements

### Hub Core
- Hub Version: >=1.0.0
- Schema Version: 3.0.0

### Server Environment
- PHP: 8.2 or higher
- MySQL: 8.0 or higher

### Dependencies
- Core Hub authentication system
- Separate `woodson_students` database with `students` table
- PDO MySQL extension

## Installation

### 1. Upload Package
- Navigate to **Admin → Package Manager** in The Hub
- Click **Upload Package**
- Select the `student-directory_1.0.0.hubpkg` file
- System will validate structure and compatibility

### 2. Database Setup
The package expects a `woodson_students` database with the following schema:

```sql
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    grade VARCHAR(5) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    nickname VARCHAR(50),
    sex CHAR(1),
    dob DATE,
    hispanic_latino TINYINT(1) DEFAULT 0,
    white TINYINT(1) DEFAULT 0,
    black_african_american TINYINT(1) DEFAULT 0,
    asian TINYINT(1) DEFAULT 0,
    american_indian_alaskan_native TINYINT(1) DEFAULT 0,
    hawaiian_pacific_isl TINYINT(1) DEFAULT 0,
    graduation_year INT,
    chromebook_login VARCHAR(100),
    group_email VARCHAR(100),
    password VARCHAR(100),
    ou_for_google VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    updated_by INT,
    INDEX idx_grade (grade),
    INDEX idx_graduation_year (graduation_year),
    INDEX idx_last_name (last_name),
    INDEX idx_student_id (student_id)
);
```

### 3. Configure Database Connection
Add the `woodson_students` connection to Hub's database configuration:

```php
// config/database.php or .env
STUDENT_DB_HOST=localhost
STUDENT_DB_NAME=woodson_students
STUDENT_DB_USER=your_user
STUDENT_DB_PASS=your_password
```

### 4. Assign Roles
After installation:
1. Navigate to **Admin → Sections** → Student Directory
2. Set role access (which Hub roles can see the Student Directory)
3. Package roles (viewer/editor/importer) are auto-mapped via Google Groups

## Architecture

### Package Components

```
packages/district/student-directory/
├── package.json                    # Package definition (Layer 3 manifest)
├── student-directory_1.0.0.hubpkg  # Distributable package file
└── README.md                       # This file

src/Package/StudentDirectory/
├── StudentDirectoryHandler.php     # Unified query/mutation bridge
├── StudentDatabase.php             # PDO singleton for woodson_students
├── PasswordGenerator.php           # Grade-based password algorithm
├── StudentQueryHandler.php         # Search, list, filter, stats queries
├── StudentMutationHandler.php      # CRUD, bulk delete, password reset
├── ImportHandler.php               # CSV import with preview/mapping
└── ExportHandler.php               # Standard & Google Workspace CSV export

public/api/student-directory.php    # Dedicated REST API endpoint (18 actions)
public/assets/js/student-directory.js # Client-side UI controller
```

### Pages (Defined in package.json)

| Page | Route | Description |
|------|-------|-------------|
| **index** | `/` | Dashboard with stats cards, search/filter, student table |
| **view** | `/view/{id}` | Full student detail with actions |
| **add** | `/add` | Add student form with auto-credential generation |
| **edit** | `/edit/{id}` | Edit student form pre-populated with current data |
| **import** | `/import` | CSV upload & import with history table |
| **export** | `/export` | Export options (Standard CSV, Google CSV, Print Cards) |

### Queries

| Query | Handler | Description |
|-------|---------|-------------|
| `listStudents` | `StudentQueryHandler::listStudents` | Paginated, sortable, filterable student list |
| `getStudent` | `StudentQueryHandler::getStudent` | Single student by ID |
| `getStats` | `StudentQueryHandler::getStats` | Dashboard totals (cached 5 min) |
| `getGrades` | `StudentQueryHandler::getGrades` | Distinct grade options (cached 1 hr) |
| `getGraduationYears` | `StudentQueryHandler::getGraduationYears` | Distinct year options (cached 1 hr) |
| `getImportHistory` | `StudentQueryHandler::getImportHistory` | Recent CSV import records |

### Mutations

| Mutation | Handler | Audit | Description |
|----------|---------|-------|-------------|
| `createStudent` | `StudentMutationHandler::createStudent` | ✅ | Add student with auto-generated credentials |
| `updateStudent` | `StudentMutationHandler::updateStudent` | ✅ | Update student record |
| `resetPassword` | `StudentMutationHandler::resetPassword` | ✅ | Reset to grade-based default password |
| `bulkDelete` | `StudentMutationHandler::bulkDelete` | ✅ | Delete selected students (with confirmation) |
| `printCards` | `StudentMutationHandler::printCards` | — | Generate printable login cards |

### Password Generation Algorithm

Passwords are generated based on grade level:

| Grades | Pattern | Example |
|--------|---------|---------|
| PK–4 | `Firstname` + last 4 of Student ID | `John1234` |
| 5–12 | `Firstname` + last 4 of Student ID + `!` | `John1234!` |

First name is capitalized; if student has a nickname, the nickname is used instead.

## API Reference

The package also exposes a direct REST API at `/api/student-directory.php` with 18 actions:

| Action | Method | Description |
|--------|--------|-------------|
| `list` | GET | List/search students |
| `get` | GET | Get single student |
| `stats` | GET | Dashboard statistics |
| `grades` | GET | Grade filter options |
| `create` | POST | Add student |
| `update` | POST | Update student |
| `delete` | POST | Delete student |
| `bulkDelete` | POST | Delete multiple students |
| `resetPassword` | POST | Reset student password |
| `importPreview` | POST | Preview CSV import |
| `importExecute` | POST | Execute CSV import |
| `importHistory` | GET | Import history |
| `exportStandard` | GET | Download standard CSV |
| `exportGoogle` | GET | Download Google Workspace CSV |
| `printCards` | POST | Generate login cards |
| `printCard` | GET | Single login card |
| `graduationYears` | GET | Graduation year options |
| `search` | GET | Quick search |

## Testing

The package includes comprehensive PHPUnit tests:

```
tests/Package/StudentDirectory/
├── StudentDirectoryHandlerTest.php  # 12 tests — handler bridge
├── PasswordGeneratorTest.php        # 43 tests — password algorithm
├── StudentQueryHandlerTest.php      # 14 tests — search/filter/stats
├── StudentMutationHandlerTest.php   # 13 tests — CRUD/bulk operations
└── ExportHandlerTest.php            # 7 tests — CSV export

tests/Package/PackageLoaderDistrictTest.php  # 8 tests — district loading
```

**Total: 97 tests, all passing**

Run tests:
```bash
./vendor/bin/phpunit tests/Package/StudentDirectory/ --testdox
```

## Support

- **Repository:** https://github.com/R1CH4RD25/TheHub
- **Issues:** https://github.com/R1CH4RD25/TheHub/issues
- **Documentation:** https://github.com/R1CH4RD25/TheHub-Package-Repo/tree/main/packages/district/student-directory
- **Contact:** tech@woodsonisd.net

## Roadmap

### Version 1.1 (Planned)
- Student photo management
- Batch password reset by grade/school
- Advanced search with demographic filters
- Student transfer tracking between schools

### Version 1.2 (Planned)
- Google Workspace API direct sync
- Automated OU assignment on import
- Parent/guardian contact fields
- Attendance integration hooks

### Version 2.0 (Future)
- Multi-district support
- SIS (Student Information System) real-time sync
- Student portal self-service
- Enrollment workflow with approval chain

## License

Proprietary software developed for Woodson ISD. All rights reserved.

## Credits

Developed by the Woodson ISD Technology Department as part of The Hub modular platform initiative.
