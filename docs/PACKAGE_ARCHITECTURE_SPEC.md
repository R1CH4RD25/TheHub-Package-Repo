# Hub Package Architecture Specification
**Version**: 1.0.0-draft
**Date**: February 11, 2026
**Status**: Design & Planning Phase

---

## Executive Summary

A Hub "package" should be describable as a **JSON-driven mini-app**, where JSON defines:
- **What the UI is** (pages, components, layouts)
- **What data it binds to** (queries, mutations)
- **What actions exist** (CRUD, workflows, approvals)
- **Who is allowed to do what** (RBAC, field-level policies)

The Hub core enforces these rules uniformly across every package, eliminating ad-hoc implementations and security inconsistencies.

### Specification Authority

**This document is the single source of truth for Layer 3 packages.** All implementation, testing, and validation must conform to:
- **Sections 1-10**: Layer 3 JSON structure and usage patterns
- **Sections 11-20**: Mandatory platform runtime requirements (non-negotiable)
- **Version**: 1.0.0-draft (implementations must reference this version)

---

## 1. The Uniform Rule Set (What JSON Must Describe)

Every package consists of **three layers**:

### A) Presentation Layer (JSON → UI)

A package declares **Pages**, and each page declares **Components**:

#### Component Types
- **`table`** - Data grids with search, sort, pagination (e.g., Student Directory)
- **`form`** - Data collection with validation and workflows
- **`detail`** - Single record view with related data
- **`dashboard/cards`** - KPI displays + action queues
- **`wizard`** - Multi-step guided workflows
- **`report`** - Read-only data exports (PDF, Excel, CSV)

#### Each Component Declares
- **Data source** (query name)
- **Columns/fields** (with types, labels, validation)
- **Filters/search/sort** (client-side and server-side)
- **Row actions** (View / Edit / Delete / Approve / Export / Reveal)
- **Client behaviors** (pagination, debounce, confirmation dialogs)

---

### B) Data Layer (JSON → Queries/Mutations)

A package declares named **queries** and **mutations** (think "API contract"):

#### Queries
Return data for tables, dropdowns, detail panels:
```json
{
  "students.search": { "handler": "StudentDirectory@search" },
  "grades.list": { "handler": "StudentDirectory@grades" }
}
```

#### Mutations
Perform state changes (create/update/delete/approve/reveal):
```json
{
  "students.revealPassword": {
    "handler": "StudentDirectory@revealPassword",
    "auditEvent": "student.password_reveal",
    "policy": "student_password_reveal"
  }
}
```

#### Hub Core Provides Consistent Plumbing
- Authentication & authorization
- CSRF protection
- Request validation
- Rate limiting
- Audit logging hooks
- Database transaction management

---

### C) Policy Layer (JSON → RBAC + Field-Level Rules)

**Critical for security-sensitive operations** (e.g., teachers revealing student passwords)

#### Policies Must Cover

1. **Page Access** - Who can see the page?
2. **Row Scope** - Which records can they see?
3. **Field Masking** - Which fields are hidden/masked?
4. **Action Gating** - Which actions are allowed?
5. **Extra Conditions** - Additional requirements (MFA, justification, logging, timeouts)

#### Example Policy
```json
{
  "student_password_reveal": {
    "allowRoles": ["teacher"],
    "scope": "teacher_of_record",
    "requiresMfa": false,
    "rateLimit": "5/min",
    "logFields": ["student_id", "reason"],
    "timeout": 30
  }
}
```

---

## 2. Real-World Example: Student Directory

### The Use Case
Teachers need to:
- Search students by name, ID, or email
- Filter by grade level
- View student information
- **Reveal passwords** (with strict controls)

### Security Requirements
- Teachers only see students assigned to them
- Passwords are **masked by default** (`••••••`)
- Password reveal requires:
  - Reason (dropdown: parent request / enrollment / troubleshooting / admin request)
  - Audit logging (who, what, when, why)
  - Rate limiting (prevent abuse)
  - Time-limited display (30-60 seconds)

---

## 3. Sensitive Data Rule: "Reveal" Must Be An Action, Not A Column

### ❌ WRONG: Password as a visible column
```json
{
  "key": "password",
  "label": "Password",
  "type": "text"
}
```
**Problem**: Exposes sensitive data in table payloads, API responses, logs

---

### ✅ CORRECT: Password masked + reveal action
```json
{
  "columns": [
    {
      "key": "password_masked",
      "label": "Password",
      "style": "masked",
      "value": "••••••"
    }
  ],
  "rowActions": [
    {
      "id": "reveal_password",
      "label": "Show",
      "type": "mutation",
      "mutation": "students.revealPassword",
      "requires": {
        "reason": true,
        "reconfirm": true
      },
      "cooldownSeconds": 30
    }
  ]
}
```

#### Reveal Workflow
1. User clicks "Show" button
2. Modal appears requesting reason
3. Server checks policy:
   - Is user a teacher?
   - Are they teacher-of-record for this student?
   - Have they exceeded rate limit?
4. Audit event logged:
   - Actor user ID
   - Student ID
   - Reason provided
   - Timestamp, IP, device
5. Server returns **time-limited token** or one-time value
6. UI displays password for 30-60 seconds
7. Password auto-hides (or requires new reveal)

#### Security Controls
- ✅ Never include secret in main table query
- ✅ Limit reveals per minute (rate limit)
- ✅ Auto-hide after timeout
- ✅ Comprehensive audit trail
- ✅ Requires explicit justification

---

## 4. Concrete JSON Package Structure

### Complete Student Directory Package

```json
{
  "schemaVersion": 2,
  "package": {
    "package_id": "com.woodson.student-directory",
    "display_name": "Student Directory",
    "version": "1.0.0",
    "author": "Woodson ISD",
    "category": "student",
    "description": "Search and manage student information with secure credential access",

    "hub_cards": [
      {
        "id": "student_directory",
        "title": "Student Directory",
        "description": "Search and view student info",
        "icon": "bi-people",
        "route": "/student-directory",
        "access": ["teacher", "admin", "super_admin"]
      }
    ]
  },

  "pages": [
    {
      "id": "student_directory_index",
      "route": "/student-directory",
      "title": "Student Directory",
      "subtitle": "Search and view student information",
      "layout": "standard",
      "access": ["teacher", "admin", "super_admin"],

      "components": [
        {
          "type": "filters",
          "id": "directory_filters",
          "fields": [
            {
              "type": "search",
              "param": "q",
              "placeholder": "Name, ID, or email...",
              "debounce": 300
            },
            {
              "type": "select",
              "param": "grade",
              "label": "Grade",
              "optionsQuery": "grades.list",
              "allowAll": true
            }
          ]
        },

        {
          "type": "table",
          "id": "student_table",
          "query": "students.search",
          "pagination": {
            "pageSize": 50,
            "pageSizeOptions": [25, 50, 100, 200]
          },

          "columns": [
            {
              "key": "student_id",
              "label": "Student ID",
              "sortable": true,
              "width": "120px"
            },
            {
              "key": "name",
              "label": "Name",
              "sortable": true,
              "width": "200px"
            },
            {
              "key": "grade",
              "label": "Grade",
              "sortable": true,
              "style": "badge",
              "badgeColor": "primary",
              "width": "80px"
            },
            {
              "key": "chromebook_login",
              "label": "Chromebook Login",
              "style": "link",
              "copyable": true,
              "width": "250px"
            },
            {
              "key": "password_masked",
              "label": "Password",
              "style": "masked",
              "value": "••••••",
              "width": "150px"
            }
          ],

          "rowActions": [
            {
              "id": "view",
              "label": "View",
              "icon": "bi-eye",
              "type": "route",
              "to": "/student-directory/{student_id}",
              "variant": "primary"
            },
            {
              "id": "reveal_password",
              "label": "Show",
              "icon": "bi-unlock",
              "type": "mutation",
              "mutation": "students.revealPassword",
              "requires": {
                "reason": true,
                "reasonOptions": [
                  "Parent request",
                  "Enrollment",
                  "Troubleshooting",
                  "Admin request"
                ],
                "reconfirm": true,
                "confirmMessage": "This action will be logged. Continue?"
              },
              "cooldownSeconds": 30,
              "variant": "warning"
            }
          ],

          "bulkActions": [
            {
              "id": "export",
              "label": "Export Selected",
              "icon": "bi-download",
              "type": "mutation",
              "mutation": "students.export",
              "format": "excel",
              "access": ["admin", "super_admin"]
            }
          ]
        }
      ]
    },

    {
      "id": "student_directory_detail",
      "route": "/student-directory/{student_id}",
      "title": "Student Details",
      "layout": "detail",
      "access": ["teacher", "admin", "super_admin"],

      "components": [
        {
          "type": "detail",
          "id": "student_detail",
          "query": "students.getById",
          "sections": [
            {
              "title": "Basic Information",
              "fields": [
                { "key": "student_id", "label": "Student ID" },
                { "key": "name", "label": "Full Name" },
                { "key": "grade", "label": "Grade" },
                { "key": "dob", "label": "Date of Birth", "type": "date" },
                { "key": "homeroom", "label": "Homeroom" }
              ]
            },
            {
              "title": "Account Information",
              "fields": [
                { "key": "chromebook_login", "label": "Chromebook Login" },
                { "key": "email", "label": "Email Address" },
                { "key": "account_status", "label": "Status", "type": "badge" }
              ]
            }
          ]
        }
      ]
    }
  ],

  "queries": {
    "students.search": {
      "handler": "StudentDirectory@search",
      "description": "Search students with filters",
      "params": {
        "q": { "type": "string", "optional": true },
        "grade": { "type": "string", "optional": true },
        "page": { "type": "integer", "default": 1 },
        "pageSize": { "type": "integer", "default": 50 }
      },
      "returns": {
        "data": "array",
        "total": "integer",
        "page": "integer",
        "pageSize": "integer"
      }
    },

    "students.getById": {
      "handler": "StudentDirectory@getById",
      "description": "Get single student record",
      "params": {
        "student_id": { "type": "integer", "required": true }
      },
      "returns": "object"
    },

    "grades.list": {
      "handler": "StudentDirectory@grades",
      "description": "Get list of grade levels",
      "returns": "array"
    }
  },

  "mutations": {
    "students.revealPassword": {
      "handler": "StudentDirectory@revealPassword",
      "description": "Reveal student password (audited action)",
      "auditEvent": "student.password_reveal",
      "policy": "student_password_reveal",
      "params": {
        "student_id": { "type": "integer", "required": true },
        "reason": { "type": "string", "required": true }
      },
      "returns": {
        "token": "string",
        "expiresAt": "timestamp"
      }
    },

    "students.export": {
      "handler": "StudentDirectory@export",
      "description": "Export student data",
      "auditEvent": "student.export",
      "policy": "student_export",
      "params": {
        "student_ids": { "type": "array", "required": true },
        "format": { "type": "string", "enum": ["excel", "csv", "pdf"] }
      },
      "returns": {
        "downloadUrl": "string",
        "expiresAt": "timestamp"
      }
    }
  },

  "policies": {
    "student_password_reveal": {
      "description": "Controls who can reveal student passwords",
      "allowRoles": ["teacher", "admin", "super_admin"],
      "scope": "teacher_of_record",
      "requiresMfa": false,
      "rateLimit": "5/min",
      "logFields": ["student_id", "reason", "user_id", "ip_address"],
      "timeout": 30,
      "alertOn": "excessive_use"
    },

    "student_export": {
      "description": "Controls who can export student data",
      "allowRoles": ["admin", "super_admin"],
      "scope": "campus",
      "auditRequired": true,
      "rateLimit": "10/hour"
    }
  },

  "auditEvents": {
    "student.password_reveal": {
      "severity": "high",
      "category": "security",
      "retentionDays": 2555,
      "notifyRoles": ["super_admin"],
      "fields": ["actor_id", "student_id", "reason", "timestamp", "ip_address"]
    },

    "student.export": {
      "severity": "medium",
      "category": "data_access",
      "retentionDays": 365,
      "fields": ["actor_id", "record_count", "format", "timestamp"]
    }
  },

  "database": {
    "migrations": [
      "CREATE TABLE IF NOT EXISTS student_password_reveals (...)",
      "CREATE INDEX idx_student_reveals ON student_password_reveals(student_id, created_at)"
    ]
  }
}
```

---

## 5. How This Fits "Cards → Mini Website" Model

### Hub Card Behavior

A Hub card maps to either:

1. **Single page** - Direct route to one view
2. **Page group** - Internal router with multiple views

#### Example: Student Directory Card

```
Hub Card: "Student Directory"
  ├── /student-directory (table view)
  ├── /student-directory/:id (detail view)
  └── /student-directory/:id/history (audit view)
```

### Consistent JSON Conventions
All packages follow the same structure:
- **`pages[]`** - UI routes and components
- **`queries`** - Data retrieval
- **`mutations`** - State changes
- **`policies`** - Access control
- **`auditEvents`** - Security logging

This prevents every package from inventing ad-hoc structures.

---

## 6. Non-Negotiables for Teacher-Only Sensitive Reporting

### Baseline Security Controls

For student credentials or other sensitive data:

✅ **Field-level masking by default** - No secrets in list payloads
✅ **Reveal is a mutation** - Policy + audit + rate limit
✅ **Scope enforcement** - Teachers only see assigned students
✅ **Audit trail is mandatory** - Who, what, when, why
✅ **No bulk delete** - Except admin (if at all)
✅ **Rate limiting** - Prevent abuse and data exfiltration
✅ **Time-limited access** - Auto-hide sensitive data
✅ **Justification required** - Reason for every reveal

### Critical Note on Bulk Delete
"Delete all matching" on a student directory is **dangerous** unless:
- Admin-only capability
- Requires explicit confirmation
- Soft-delete (not hard delete)
- Comprehensive audit trail
- Reversible within X days

Consider **disabling bulk delete entirely** for student records.

---

## 7. Practical Implementation Action Plan

### Phase 1: Foundation (Weeks 1-2)
1. **Define v1 UI DSL JSON schema**
   - Start small: `table` + `form` + `detail` components
   - Define `queries` and `mutations` contract
   - Specify `policies` and `auditEvents` structure

2. **Build core renderer**
   - Generic table component that consumes JSON
   - Generic form component with validation
   - Generic detail view component
   - Filter/search/pagination handlers

### Phase 2: Security Layer (Weeks 3-4)
3. **Build policy enforcement engine**
   - Role-based access control (RBAC)
   - Row-level security (RLS) / scope checks
   - Field-level masking
   - Action gating (mutation policies)
   - Rate limiting middleware

4. **Implement "sensitive reveal" pattern**
   - Standard behavior: reason required
   - Audit logging (comprehensive)
   - Time-limited tokens
   - Rate limiting
   - UI components (modal, countdown timer)

### Phase 3: Documentation & Standards (Week 5)
5. **Official Package Standard Document**
   - JSON schema specification
   - Component type reference
   - Policy examples
   - Security best practices
   - Migration guide from Layer 1 packages

### Phase 4: Migration & Testing (Weeks 6-8)
6. **Migrate existing packages**
   - Vehicle Maintenance → Layer 3 format
   - Student Directory → new implementation
   - Test all security controls
   - Performance benchmarking

---

## 8. JSON Schema Evolution

### Layer 1 (Legacy)
Simple field arrays, basic permissions
```json
{
  "fields": [...],
  "permissions": [...]
}
```

### Layer 2 (Current)
Entities, migrations, workflows
```json
{
  "entities": [...],
  "database": { "migrations": [...] },
  "workflow_states": [...]
}
```

### Layer 3 (Proposed)
Full UI DSL with security policies
```json
{
  "pages": [...],
  "queries": {...},
  "mutations": {...},
  "policies": {...},
  "auditEvents": {...}
}
```

---

## 9. API Contract Example

### Query Handler
```php
class StudentDirectoryController {
    public function search(Request $request): JsonResponse {
        // Policy enforced by middleware
        // $request->user() already scoped to allowed students

        $query = Student::query()
            ->whereIn('student_id', $request->user()->getAssignedStudents())
            ->when($request->q, fn($q) => $q->search($request->q))
            ->when($request->grade, fn($q) => $q->where('grade', $request->grade));

        return response()->json([
            'data' => $query->paginate($request->pageSize),
            'total' => $query->count()
        ]);
    }

    public function revealPassword(Request $request): JsonResponse {
        // Policy already checked by middleware
        // Rate limit already enforced
        // Scope already validated

        $student = Student::findOrFail($request->student_id);
        
        // Decrypt password
        $plaintext = decrypt($student->password);

        // Create time-limited token (NEVER return raw password)
        $token = Str::random(32);
        Cache::put("reveal:{$token}", $plaintext, now()->addSeconds(30));

        // Log audit event (token only, NOT password)
        AuditLogger::log('student.password_reveal', [
            'student_id' => $student->id,
            'reason' => $request->reason,
            'actor_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'token' => $token
        ]);

        // Return token for separate fetch
        return response()->json([
            'token' => $token,
            'expiresAt' => now()->addSeconds(30)->timestamp
        ]);
    }
}
```

---

## 10. Next Steps

### Immediate Actions
1. Review this specification with development team
2. Identify first pilot package (Student Directory recommended)
3. Define minimum viable DSL (start with table + reveal action)
4. Build proof-of-concept renderer
5. Test security controls thoroughly

### Questions to Answer
- How do we handle package upgrades (Layer 2 → Layer 3)?
- What's the migration path for existing packages?
- Do we auto-generate handlers or require manual implementation?
- How do we version the DSL schema?
- What's the developer experience for creating new packages?

### Success Metrics
- ✅ All packages use consistent JSON structure
- ✅ Zero ad-hoc security implementations
- ✅ Comprehensive audit coverage (100% of sensitive actions)
- ✅ Developer velocity (new package in < 1 day)
- ✅ Security review simplified (check JSON, not code)

---

## 11. Package Runtime Contract

### What Makes It "Mini Apps Inside Hub" Without Chaos

Every Layer 3 package **must declare** these top-level properties:

#### Package Metadata
```json
{
  "package": {
    "package_id": "com.woodson.student-directory",
    "version": "1.0.0",
    "type": "reporting",
    "publisher": "woodson-isd",
    "signature": "SHA256:abc123...",
    "publicKeyId": "woodson-2026"
  }
}
```

#### Package Types
- **`recording`** - Data entry (fuel logs, maintenance events)
- **`reporting`** - Read-heavy analytics (student directory, dashboards)
- **`admin`** - System configuration (user management, settings)
- **`dashboard`** - KPI aggregation (homepage widgets)

#### Lifecycle Stages
```
install → validate → enable → route-bind → run → upgrade → disable → uninstall → rollback
```

**Hub Core Enforces**:
1. **Install**: Verify signature, check compatibility, validate handler registry
2. **Validate**: JSON schema check, handler interface compliance, policy completeness
3. **Enable**: Register routes, bind handlers, apply migrations
4. **Run**: Enforce policies on every request
5. **Upgrade**: Version check, migration path validation, rollback snapshot
6. **Disable**: Unbind routes (but preserve data)
7. **Uninstall**: Drop tables (if requested), remove audit logs (retention policy)
8. **Rollback**: Restore previous version from snapshot

#### Compatibility Declaration
```json
{
  "compatibility": {
    "minHubVersion": "3.0.0",
    "maxHubVersion": "3.99.99",
    "phpVersion": ">=8.1",
    "laravelVersion": "^10.0",
    "dbDriver": "mysql",
    "schemaVersion": "2024.11"
  }
}
```

#### Dependencies
```json
{
  "dependencies": [
    {
      "package_id": "com.woodson.user-management",
      "versionRange": "^2.0.0",
      "required": true
    },
    {
      "package_id": "com.woodson.notification-system",
      "versionRange": ">=1.5.0",
      "required": false
    }
  ]
}
```

**Hub checks dependencies** at install time and prevents orphan packages.

#### Capabilities (Declared, Not Implied)
```json
{
  "capabilities": [
    "ui.pages",
    "data.queries",
    "data.mutations",
    "exports.excel",
    "exports.pdf",
    "jobs.background",
    "sensitive_actions",
    "assets.js",
    "assets.css",
    "notifications.email",
    "webhooks.outbound"
  ]
}
```

**Why this matters**: Hub can pre-flight check if required services are available (e.g., queue worker, PDF renderer).

#### Settings Model
```json
{
  "packageSettings": {
    "default_page_size": {
      "type": "integer",
      "default": 50,
      "min": 10,
      "max": 200,
      "scope": "admin"
    },
    "enable_password_reveal": {
      "type": "boolean",
      "default": true,
      "scope": "super_admin"
    }
  },
  "userPreferences": {
    "rows_per_page": {
      "type": "integer",
      "default": 50,
      "options": [25, 50, 100, 200]
    },
    "card_compact_mode": {
      "type": "boolean",
      "default": false
    }
  }
}
```

**Storage**:
- `package_settings` table: `(package_id, key, value, scope)`
- `user_package_preferences` table: `(user_id, package_id, key, value)`

---

## 12. Enforcement Pipelines (Non-Optional)

### The Central Enforcement Contract

**Hub Core** must enforce policies at these checkpoints—**never delegate to package handlers**.

#### Page Access Enforcement (Pre-Render)

**Before rendering any page**, Hub must verify:
```php
class PackagePageController {
    public function render($packageId, $pageId) {
        $package = PackageRegistry::get($packageId);
        $page = $package->pages[$pageId];
        
        // HARD STOP: Check page-level access
        if (!PolicyEngine::canAccessPage(Auth::user(), $page['access'] ?? [])) {
            abort(403, 'You do not have access to this page');
        }
        
        // HARD STOP: Check scope (does user have ANY records to see?)
        if ($page['requiresScope'] ?? false) {
            if (!ScopeEngine::hasAnyAccess(Auth::user(), $page['scope'])) {
                abort(403, 'No records available in your scope');
            }
        }
        
        // Proceed to render components (each will apply own scope)
        return view('package.page', compact('package', 'page'));
    }
}
```

**Critical Rule**: If page access fails, **no component queries execute**. This prevents accidental data exposure.

#### Query Execution Pipeline
```
Request
  ↓
[1] Authenticate (session/token)
  ↓
[2] Resolve package + query name from route
  ↓
[3] Policy check: Can user execute this query?
  ↓
[4] Apply scope filter (row-level security)
  ↓
[5] Execute handler (with scoped query builder)
  ↓
[6] Apply projection/masking (field-level security)
  ↓
[7] Audit (optional for queries, mandatory for sensitive)
  ↓
Response (JSON)
```

#### Mutation Execution Pipeline
```
Request
  ↓
[1] Authenticate
  ↓
[2] CSRF verification
  ↓
[3] Resolve package + mutation name
  ↓
[4] Rate limit check
  ↓
[5] Policy check: Can user execute this mutation?
  ↓
[6] Input validation (JSON schema or DTO)
  ↓
[7] Begin transaction
  ↓
[8] Execute handler
  ↓
[9] Audit event (mandatory)
  ↓
[10] Commit transaction
  ↓
Response (JSON)
```

### Scope Enforcement (Server-Side)

**Never trust UI to filter data**. Scope is applied by Hub Core before the handler runs.

```php
class QueryRouter {
    public function execute(string $packageId, string $queryName, Request $request): array {
        $package = PackageRegistry::get($packageId);
        $queryDef = $package->queries[$queryName];

        // Policy check
        $policy = $queryDef['policy'] ?? null;
        if ($policy && !PolicyEngine::check($request->user(), $policy)) {
            throw new UnauthorizedException();
        }

        // Apply scope (row-level security)
        $scopedBuilder = ScopeEngine::apply(
            $request->user(),
            $queryDef['scope'] ?? 'global',
            DB::table($queryDef['table'])
        );

        // Execute handler with scoped query builder
        $handler = HandlerRegistry::resolve($queryDef['handler']);
        $results = $handler->handle($scopedBuilder, $request->all());

        // Apply field masking
        return ProjectionEngine::mask($results, $queryDef['columns'] ?? []);
    }
}
```

### Example Scope Types

```php
class ScopeEngine {
    public static function apply(User $user, string $scope, QueryBuilder $builder): QueryBuilder {
        return match($scope) {
            'global' => $builder,
            'campus' => $builder->where('campus_id', $user->campus_id),
            'teacher_of_record' => $builder->whereIn('student_id', $user->getAssignedStudents()),
            'self_only' => $builder->where('user_id', $user->id),
            'district' => $builder->where('district_id', $user->district_id),
            default => throw new InvalidScopeException("Unknown scope: {$scope}")
        };
    }
}
```

### Field Masking (Server-Side)

```php
class ProjectionEngine {
    public static function mask(array $results, array $columnDefs): array {
        return array_map(function($row) use ($columnDefs) {
            foreach ($columnDefs as $col) {
                if (($col['style'] ?? null) === 'masked' && isset($row[$col['key']])) {
                    $row[$col['key']] = '••••••';
                }
                if (($col['secret'] ?? false) && isset($row[$col['key']])) {
                    unset($row[$col['key']]); // Remove entirely
                }
            }
            return $row;
        }, $results);
    }
}
```

**Critical Rule**: Field masking happens **after** the handler returns data, **before** the response leaves Hub Core.

---

## 13. Security & Trust (Supply Chain)

### Package Signing & Verification

If packages can be uploaded or installed from external sources, **signing is mandatory**.

#### Publisher Registration
```json
{
  "publishers": [
    {
      "id": "woodson-isd",
      "name": "Woodson Independent School District",
      "publicKey": "-----BEGIN PUBLIC KEY-----\n...",
      "keyId": "woodson-2026",
      "trusted": true
    }
  ]
}
```

#### Package Signature
```json
{
  "package": {
    "publisher": "woodson-isd",
    "signature": "SHA256:abc123def456...",
    "signedAt": "2026-02-01T10:00:00Z",
    "publicKeyId": "woodson-2026"
  }
}
```

#### Verification Process
```php
class PackageInstaller {
    public function install(string $packagePath): bool {
        $packageData = json_decode(file_get_contents($packagePath), true);

        // 1. Verify signature
        $publisher = PublisherRegistry::get($packageData['package']['publisher']);
        if (!$publisher->isTrusted()) {
            throw new UntrustedPublisherException();
        }

        $publicKey = $publisher->getPublicKey();
        $signature = $packageData['package']['signature'];
        $payload = $this->getSignablePayload($packageData);

        if (!openssl_verify($payload, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA256)) {
            throw new InvalidSignatureException();
        }

        // 2. Store hash manifest for audit
        $hash = hash('sha256', $payload);
        DB::table('package_installations')->insert([
            'package_id' => $packageData['package']['package_id'],
            'version' => $packageData['package']['version'],
            'hash' => $hash,
            'publisher' => $packageData['package']['publisher'],
            'installed_at' => now(),
            'installed_by' => Auth::id()
        ]);

        // 3. Proceed with installation...
    }
}
```

#### Development Mode
```env
PACKAGE_DEV_MODE=true
PACKAGE_ALLOW_UNSIGNED=true
```

**Production**: Both flags **must** be `false`.

---

## 14. Handler Registry & Safety

### The Problem

Allowing arbitrary `"handler": "Class@method"` in JSON is dangerous:
- Reflection abuse
- Container resolution attacks
- Uninspectable code paths

### The Solution: Handler Registry

#### At Package Install Time
```json
{
  "handlers": {
    "StudentDirectory@search": {
      "class": "App\\Packages\\StudentDirectory\\Handlers\\SearchHandler",
      "method": "handle",
      "interface": "QueryHandlerInterface",
      "version": "1.0.0"
    },
    "StudentDirectory@revealPassword": {
      "class": "App\\Packages\\StudentDirectory\\Handlers\\RevealPasswordHandler",
      "method": "handle",
      "interface": "MutationHandlerInterface",
      "version": "1.0.0"
    }
  }
}
```

#### Required Interfaces
```php
interface QueryHandlerInterface {
    public function handle(QueryBuilder $builder, array $params): array;
}

interface MutationHandlerInterface {
    public function handle(array $params): array;
}
```

#### Handler Whitelisting
```php
class HandlerRegistry {
    private static array $registered = [];

    public static function register(string $packageId, string $name, array $definition): void {
        // Verify class exists
        if (!class_exists($definition['class'])) {
            throw new HandlerNotFoundException($definition['class']);
        }

        // Verify implements required interface
        $requiredInterface = $definition['interface'];
        if (!in_array($requiredInterface, class_implements($definition['class']))) {
            throw new HandlerInterfaceException(
                "{$definition['class']} must implement {$requiredInterface}"
            );
        }

        // Store whitelist entry
        self::$registered["{$packageId}::{$name}"] = $definition;
    }

    public static function resolve(string $handlerName): object {
        $key = $handlerName; // e.g., "com.woodson.student-directory::StudentDirectory@search"

        if (!isset(self::$registered[$key])) {
            throw new UnregisteredHandlerException($handlerName);
        }

        $def = self::$registered[$key];
        return app($def['class']); // Laravel container resolution (safe because whitelisted)
    }
}
```

#### Input Validation (JSON Schema or DTO)
```json
{
  "mutations": {
    "students.revealPassword": {
      "handler": "StudentDirectory@revealPassword",
      "inputSchema": {
        "type": "object",
        "required": ["student_id", "reason"],
        "properties": {
          "student_id": { "type": "integer", "minimum": 1 },
          "reason": { "type": "string", "enum": ["Parent request", "Enrollment", "Troubleshooting", "Admin request"] }
        }
      }
    }
  }
}
```

**Hub validates inputs before calling handler**.

---

## 15. Data Classification & Encryption Rules

### Why This Matters

Storing student passwords (even if they're "initial credentials") requires strict controls.

### Data Classification Levels
```
public       - Anyone can see (e.g., student name)
internal     - Authenticated users only
confidential - Role-restricted (e.g., grades)
regulated    - FERPA/HIPAA/etc. (e.g., SSN, health records)
secret       - Credentials, API keys, encryption keys
```

### Field Annotations
```json
{
  "columns": [
    {
      "key": "name",
      "label": "Name",
      "dataClass": "public"
    },
    {
      "key": "email",
      "label": "Email",
      "dataClass": "internal",
      "pii": true
    },
    {
      "key": "password",
      "label": "Password",
      "dataClass": "secret",
      "secret": true
    }
  ]
}
```

### Storage Rules

| Classification | Storage Requirement |
|----------------|---------------------|
| `public` | Plaintext OK |
| `internal` | Plaintext OK, audit access |
| `confidential` | Encrypt at rest (optional), audit access |
| `regulated` | Encrypt at rest (required), comprehensive audit |
| `secret` | Encrypt at rest + key management, never log plaintext |

### Secret Handling

**❌ NEVER**:
- Return secrets in list queries
- Return raw secrets in mutation responses (even reveal mutations)
- Log secrets in audit events (log `"<redacted>"` instead)
- Store secrets in plaintext

**✅ ALWAYS**:
- Encrypt secrets at rest (Laravel encryption or database-level encryption)
- Use time-limited tokens for reveals (stored in Cache, not returned directly)
- Client fetches secret via separate token endpoint (single use)
- Audit every access (log token, not secret)
- Auto-expire revealed secrets (30-60 seconds max)

**Mandatory Pattern for `dataClass: "secret"`**:
```
Reveal mutation → returns token
Client calls GET /api/reveal/{token} → returns actual secret
Token consumed (single use) or expires after timeout
```

#### Example: Encrypted Password Storage
```php
// Store
$student->password = encrypt($plaintextPassword);

// Reveal (mutation only)
public function revealPassword(int $studentId, string $reason): array {
    $student = Student::findOrFail($studentId);

    // Decrypt
    $plaintext = decrypt($student->password);

    // Create time-limited token
    $token = Str::random(32);
    Cache::put("reveal:{$token}", $plaintext, now()->addSeconds(30));

    // Audit (log token, NOT plaintext)
    AuditLogger::log('student.password_reveal', [
        'student_id' => $studentId,
        'reason' => $reason,
        'token' => $token, // Safe to log
        'expires_at' => now()->addSeconds(30)
    ]);

    return [
        'token' => $token,
        'expiresAt' => now()->addSeconds(30)->timestamp
    ];
}

// Client fetches actual password via token
GET /api/reveal/{token}
```

**Alternative**: If these are Google Workspace passwords, consider using **Google Admin SDK to reset passwords** instead of storing them.

---

## 16. Audit Event Taxonomy (Standardization)

### Why Consistent Events Matter

Ad-hoc audit logging creates:
- Inconsistent metadata
- Impossible correlation
- Compliance gaps

### Standard Event Naming
```
package.<package_id>.query.<query_name>
package.<package_id>.mutation.<mutation_name>
sensitive.<action>.requested
sensitive.<action>.approved
sensitive.<action>.revealed
sensitive.<action>.expired
package.<package_id>.lifecycle.<stage>
```

#### Examples
```
package.com.woodson.student-directory.query.search
package.com.woodson.student-directory.mutation.revealPassword
sensitive.password_reveal.requested
sensitive.password_reveal.revealed
sensitive.password_reveal.expired
package.com.woodson.vehicle-maintenance.lifecycle.install
```

### Required Metadata (All Events)
```json
{
  "event": "sensitive.password_reveal.revealed",
  "actor_id": 42,
  "tenant_id": "woodson-isd",
  "campus_id": 3,
  "target_type": "student",
  "target_id": 1234,
  "reason": "Parent request",
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "correlation_id": "uuid-1234",
  "timestamp": "2026-02-11T14:30:00Z",
  "metadata": {
    "student_id": 1234,
    "token": "abc123",
    "expires_at": "2026-02-11T14:30:30Z"
  }
}
```

### Audit Table Schema
```sql
CREATE TABLE package_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event VARCHAR(200) NOT NULL COMMENT 'Full taxonomy: package.<id>.query.<name>',
    package_id VARCHAR(100) NOT NULL,
    actor_id INT NOT NULL,
    tenant_id VARCHAR(50),
    campus_id INT,
    target_type VARCHAR(50),
    target_id VARCHAR(100),
    reason TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    correlation_id VARCHAR(36),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_event (event, created_at),
    INDEX idx_actor (actor_id, created_at),
    INDEX idx_target (target_type, target_id, created_at),
    INDEX idx_correlation (correlation_id)
) ENGINE=InnoDB;
```

**Note**: Use single `event` column containing full taxonomy. Do NOT add separate `event_type` or `action` columns (prevents fragmentation).

### The Use Case

Reporting packages always need:
- Export to Excel/CSV/PDF
- Long-running queries (> 30 seconds)
- Download tokens with expiry

### Export Action Spec
```json
{
  "bulkActions": [
    {
      "id": "export",
      "label": "Export Selected",
      "icon": "bi-download",
      "type": "export",
      "formats": ["excel", "csv", "pdf"],
      "mutation": "students.export",
      "access": ["admin", "super_admin"]
    }
  ]
}
```

### Background Job Flow
```
User clicks Export
  ↓
Hub creates job: students.export
  ↓
Job queued (Laravel Queue)
  ↓
Worker executes export
  ↓
File stored in S3/local storage
  ↓
Download token created (24-hour expiry)
  ↓
User notified (email or toast)
  ↓
User downloads via token
  ↓
Audit event logged
```

### Export Job Handler
```php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ExportStudentsJob implements ShouldQueue {
    use InteractsWithQueue, Queueable;

    public function __construct(
        public array $studentIds,
        public string $format,
        public int $userId
    ) {}

    public function handle(): void {
        // Generate export
        $exporter = new StudentExporter($this->studentIds, $this->format);
        $filePath = $exporter->generate();

        // Create download token
        $token = Str::random(32);
        DB::table('export_downloads')->insert([
            'token' => $token,
            'user_id' => $this->userId,
            'file_path' => $filePath,
            'expires_at' => now()->addHours(24),
            'created_at' => now()
        ]);

        // Notify user
        $user = User::find($this->userId);
        $user->notify(new ExportReadyNotification($token));

        // Audit
        AuditLogger::log('student.export.generated', [
            'user_id' => $this->userId,
            'record_count' => count($this->studentIds),
            'format' => $this->format,
            'token' => $token
        ]);
    }
}
```

### Download Endpoint
```php
Route::get('/exports/download/{token}', function($token) {
    $export = DB::table('export_downloads')
        ->where('token', $token)
        ->where('user_id', Auth::id())
        ->where('expires_at', '>', now())
        ->first();

    if (!$export) {
        abort(404, 'Export not found or expired');
    }

    // Audit download
    AuditLogger::log('student.export.downloaded', [
        'token' => $token,
        'user_id' => Auth::id()
    ]);

    return response()->download(storage_path($export->file_path));
});
```

### Database Tables
```sql
CREATE TABLE export_downloads (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    package_id VARCHAR(100) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size BIGINT,
    format VARCHAR(20) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    downloaded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_token (token, expires_at),
    INDEX idx_user (user_id, created_at)
) ENGINE=InnoDB;
```

---

## 18. Assets, Theming & CSP

### Package Asset Management

Mini-apps may need custom JS/CSS, but with strict controls.

#### Asset Declaration
```json
{
  "assets": {
    "js": [
      {
        "path": "assets/js/student-directory.js",
        "version": "1.0.0",
        "integrity": "sha384-abc123...",
        "defer": true
      }
    ],
    "css": [
      {
        "path": "assets/css/student-directory.css",
        "version": "1.0.0",
        "integrity": "sha384-def456..."
      }
    ]
  }
}
```

#### Asset Loading (CSP-Safe)
```blade
@foreach($package->assets['css'] ?? [] as $css)
    <link rel="stylesheet"
          href="{{ asset($css['path']) }}?v={{ $css['version'] }}"
          integrity="{{ $css['integrity'] }}"
          crossorigin="anonymous">
@endforeach

@foreach($package->assets['js'] ?? [] as $js)
    <script src="{{ asset($js['path']) }}?v={{ $js['version'] }}"
            integrity="{{ $js['integrity'] }}"
            crossorigin="anonymous"
            @if($js['defer'] ?? false) defer @endif>
    </script>
@endforeach
```

#### Content Security Policy
```php
// Middleware: CspMiddleware
public function handle($request, $next) {
    $response = $next($request);

    $response->headers->set('Content-Security-Policy', implode('; ', [
        "default-src 'self'",
        "script-src 'self'", // No inline scripts
        "style-src 'self'",  // No inline styles
        "img-src 'self' data: https:",
        "font-src 'self'",
        "connect-src 'self'",
        "frame-ancestors 'none'"
    ]));

    return $response;
}
```

**Critical Rule**: **No inline scripts or styles**. All JS/CSS must be in files with SRI hashes.

#### Design Tokens (Hub UI Kit)
```css
/* Hub core provides design tokens */
:root {
    --hub-primary: #0066cc;
    --hub-secondary: #6c757d;
    --hub-success: #28a745;
    --hub-danger: #dc3545;
    --hub-warning: #ffc107;

    --hub-font-family: system-ui, -apple-system, sans-serif;
    --hub-font-size-base: 16px;
    --hub-spacing-unit: 8px;
    --hub-border-radius: 4px;
}
```

Packages use these tokens for consistency.

---

## 19. Observability & Telemetry

### Correlation ID (Request Tracking)

Every request gets a unique ID for tracking across logs, audit events, and errors.

```php
// Middleware: CorrelationIdMiddleware
public function handle($request, $next) {
    $correlationId = $request->header('X-Correlation-ID') ?? Str::uuid();
    $request->attributes->set('correlation_id', $correlationId);

    Log::withContext(['correlation_id' => $correlationId]);

    $response = $next($request);
    $response->headers->set('X-Correlation-ID', $correlationId);

    return $response;
}
```

### Performance Metrics

Track per-request metrics for packages:

```sql
CREATE TABLE package_metrics (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    correlation_id VARCHAR(36) NOT NULL,
    package_id VARCHAR(100) NOT NULL,
    query_or_mutation VARCHAR(100) NOT NULL,
    render_time_ms INT,
    query_time_ms INT,
    row_count INT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_package (package_id, created_at),
    INDEX idx_correlation (correlation_id)
) ENGINE=InnoDB;
```

#### Slow Query Logging
```php
// After query execution
if ($queryTimeMs > config('hub.slow_query_threshold', 1000)) {
    Log::warning('Slow package query detected', [
        'package_id' => $packageId,
        'query' => $queryName,
        'time_ms' => $queryTimeMs,
        'correlation_id' => request()->get('correlation_id')
    ]);
}
```

### Package Health Dashboard (Admin-Only)

Show per-package metrics:
- Average render time
- Average query time
- Error rate (last 24h)
- Total requests
- Active users

```php
Route::get('/admin/packages/health', function() {
    $packages = DB::table('package_metrics')
        ->select(
            'package_id',
            DB::raw('AVG(render_time_ms) as avg_render_time'),
            DB::raw('AVG(query_time_ms) as avg_query_time'),
            DB::raw('COUNT(*) as request_count')
        )
        ->where('created_at', '>', now()->subDay())
        ->groupBy('package_id')
        ->get();

    return view('admin.package-health', compact('packages'));
});
```

---

## 20. Package Admin Console

### Operability Requirements

Admins need visibility into installed packages.

#### Admin Console Features

1. **Installed Packages List**
   - Package ID, version, enabled/disabled, install date
   - Instant enable/disable toggle
   - Uninstall button (with confirmation)

2. **Package Detail View**
   - Routes/pages discovered
   - Handler registry (all registered handlers)
   - Policy coverage check (% of mutations with policies)
   - Last error + stacktrace
   - Validation report (JSON schema compliance)

3. **Validation Tool**
   - "Validate Package" button
   - Checks:
     - JSON schema compliance
     - Handler classes exist
     - Handlers implement required interfaces
     - All policies reference valid scopes
     - All queries/mutations have audit events

4. **Audit Viewer (Package-Filtered)**
   - Filter audit log by package_id
   - Filter by event type (query, mutation, lifecycle)
   - Filter by severity
   - Export audit log

#### Example: Package Validation Endpoint
```php
Route::post('/admin/packages/{packageId}/validate', function($packageId) {
    $package = PackageRegistry::get($packageId);
    $validator = new PackageValidator($package);

    $results = $validator->validate();

    return response()->json([
        'valid' => $results->isValid(),
        'errors' => $results->getErrors(),
        'warnings' => $results->getWarnings(),
        'coverage' => [
            'policy_coverage' => $results->getPolicyCoverage(),
            'audit_coverage' => $results->getAuditCoverage(),
            'handler_coverage' => $results->getHandlerCoverage()
        ]
    ]);
});
```

#### Validation Checks
```php
class PackageValidator {
    public function validate(): ValidationResult {
        $errors = [];
        $warnings = [];

        // JSON schema compliance
        if (!$this->validateSchema()) {
            $errors[] = 'Package JSON does not match schema';
        }

        // Handler registry
        foreach ($this->package->handlers as $name => $handler) {
            if (!class_exists($handler['class'])) {
                $errors[] = "Handler class not found: {$handler['class']}";
            }
            if (!$this->implementsInterface($handler['class'], $handler['interface'])) {
                $errors[] = "Handler {$handler['class']} does not implement {$handler['interface']}";
            }
        }

        // Policy coverage
        $mutationsWithPolicies = 0;
        foreach ($this->package->mutations as $name => $mutation) {
            if (isset($mutation['policy'])) {
                $mutationsWithPolicies++;
            } else {
                $warnings[] = "Mutation {$name} has no policy defined";
            }
        }
        $policyCoverage = count($this->package->mutations) > 0
            ? ($mutationsWithPolicies / count($this->package->mutations)) * 100
            : 0;

        return new ValidationResult($errors, $warnings, [
            'policy_coverage' => $policyCoverage
        ]);
    }
}
```

---

## Appendix: Component Type Reference

### Table Component
```json
{
  "type": "table",
  "query": "resource.list",
  "pagination": { "pageSize": 50 },
  "columns": [...],
  "rowActions": [...],
  "bulkActions": [...]
}
```

### Form Component
```json
{
  "type": "form",
  "mutation": "resource.create",
  "sections": [
    {
      "title": "Basic Info",
      "fields": [
        { "name": "email", "type": "email", "required": true },
        { "name": "role", "type": "select", "optionsQuery": "roles.list" }
      ]
    }
  ],
  "validation": "client_and_server"
}
```

### Detail Component
```json
{
  "type": "detail",
  "query": "resource.getById",
  "sections": [
    {
      "title": "Overview",
      "layout": "grid",
      "fields": [...]
    }
  ],
  "actions": [
    { "id": "edit", "label": "Edit", "route": "/resource/{id}/edit" }
  ]
}
```

### Dashboard Component
```json
{
  "type": "dashboard",
  "cards": [
    {
      "type": "stat",
      "label": "Total Students",
      "query": "stats.studentCount",
      "icon": "bi-people",
      "color": "primary"
    },
    {
      "type": "queue",
      "label": "Pending Approvals",
      "query": "approvals.pending",
      "action": { "label": "Review", "route": "/approvals/{id}" }
    }
  ]
}
```

---

## Document Maintenance
- **Author**: AI Assistant + Development Team
- **Last Updated**: February 11, 2026
- **Next Review**: TBD after Phase 1 completion
- **Status**: Living document - will evolve with implementation

---

**End of Specification**
