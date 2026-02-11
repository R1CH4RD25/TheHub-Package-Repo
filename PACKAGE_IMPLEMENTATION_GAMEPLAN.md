# Package Architecture Implementation Game Plan

**Sprint Planning for Layer 3 Package System**
**Target Timeline**: 8-10 weeks
**Team Size**: 2-3 developers  

---

## 📋 Specification Authority

**THIS IMPLEMENTATION MUST CONFORM TO**: `PACKAGE_ARCHITECTURE_SPEC.md v1.0.0-draft`

- **Sections 1-10**: JSON structure and component definitions
- **Sections 11-20**: Mandatory runtime requirements (non-negotiable)

All code, tests, and validations reference the spec as the single source of truth. If this game plan conflicts with the spec, **the spec wins**.

Build a **JSON-driven package system** where UI, data, security, and workflows are declaratively defined, eliminating ad-hoc implementations and creating a consistent, auditable platform for all Hub applications.

---

## 📊 Current State Assessment

### What We Have (Layer 2)
✅ Package installation system (`PackageManager.php`)
✅ Database migrations from JSON
✅ Entity definitions (basic structure)
✅ Section creation + menu items
✅ Basic RBAC (section_role_access)
✅ Dynamic form renderer (`DynamicFormRenderer.php`)
✅ Workflow states (basic)

### What We Need (Layer 3)
❌ UI DSL parser (pages → components)
❌ Generic component renderers (table, form, detail)
❌ Query/Mutation router
❌ Policy engine (row-level, field-level, action-level)
❌ Sensitive action pattern ("reveal" workflow)
❌ Audit event system (comprehensive)
❌ Rate limiting middleware
❌ Package migration utilities (Layer 2 → Layer 3)

---

## 🏗️ Architecture Components Inventory

### Core System Components to Build

| Component | Priority | Complexity | Dependencies | Est. Hours | Sprint |
|-----------|----------|------------|--------------|------------|--------|
| **SPRINT 0: Platform Contract** |
| PackageValidator | P0 | Low | JSON Schema lib | 4h | 0 |
| HandlerRegistry | P0 | Medium | None | 6h | 0 |
| PolicyEngine v0 (minimal) | P0 | Medium | Auth, RateLimiter | 8h | 0 |
| QueryRouter (with enforcement) | P0 | High | Auth, PolicyEngine v0 | 12h | 0 |
| MutationRouter (with enforcement) | P0 | High | Auth, PolicyEngine v0, Rate limit | 12h | 0 |
| ScopeEngine | P0 | Medium | Query builder | 6h | 0 |
| ProjectionEngine (masking) | P0 | Medium | None | 4h | 0 |
| Enhanced AuditLogger | P0 | Medium | Existing AuditLogger | 6h | 0 |
| **SPRINT 1: UI Components** |
| JSON Schema Validator | P0 | Low | None | 4h | 1 |
| Page Router | P0 | Medium | Laravel routing | 8h | 1 |
| Component Registry | P0 | Medium | None | 6h | 1 |
| Table Renderer | P0 | High | Component Registry | 16h | 1 |
| **SPRINT 2: Security Layer** |
| PolicyEngine v1 (advanced) | P0 | High | PolicyEngine v0 | 16h | 2 |
| Rate Limiter | P0 | Low | Laravel cache | 4h | 2 |
| Sensitive Action Handler (token-based) | P0 | High | PolicyEngine v1 + Audit + Rate limit | 14h | 2 |
| Package Signing & Verification | P1 | Medium | OpenSSL | 8h | 2 |
| **SPRINT 3: Forms & Exports** |
| Form Renderer | P1 | High | DynamicFormRenderer (extend) | 12h | 3 |
| Detail Renderer | P1 | Medium | Component Registry | 8h | 3 |
| Export Handler | P1 | Medium | Queue system | 10h | 3 |
| Background Job Infrastructure | P1 | Medium | Laravel Queue | 8h | 3 |
| **SPRINT 4: Ops & Observability** |
| Correlation ID Middleware | P1 | Low | None | 2h | 4 |
| Performance Metrics Tracker | P1 | Medium | Database | 6h | 4 |
| Package Admin Console | P1 | High | All components | 16h | 4 |
| Package Health Dashboard | P1 | Medium | Metrics | 8h | 4 |
| Package Migrator (Layer 2→3) | P2 | Medium | PackageManager | 8h | 4 |

**Total Estimated**: ~186 hours (5-6 weeks with 2 devs)

**Note**: Sprint 0 adds ~50 hours but is **non-negotiable** for security and maintainability.

---

## 📅 Sprint Breakdown

### Sprint 0: Platform Contract & Safety (Week 0 - 2-3 days)
**Goal**: Define platform-level contracts before building renderers

**Critical**: Do NOT skip this. Building UI first and backfilling security is how we end up with Layer 2 problems.

#### Day 1: Package Runtime Contract
- [ ] **Define Package Metadata Schema**
  - Package types (`recording`, `reporting`, `admin`, `dashboard`)
  - Lifecycle stages (install → validate → enable → run → upgrade → disable)
  - Compatibility declaration (Hub version, PHP, Laravel, DB)
  - Dependencies (package A requires package B)
  - Capabilities (ui.pages, exports, jobs, sensitive_actions)
  - Settings model (packageSettings, userPreferences)

- [ ] **Create PackageValidator Class**
  - JSON schema validation
  - Compatibility checking
  - Dependency resolution
  - Signature verification (if packages can be uploaded)

#### Day 2: Handler Registry & Safety
- [ ] **Define Handler Interfaces**
  - `QueryHandlerInterface` with `handle(QueryBuilder $builder, array $params): array`
  - `MutationHandlerInterface` with `handle(array $params): array`
  - `ExportHandlerInterface` with `handle(array $params): string`

- [ ] **Build HandlerRegistry Class**
  - Whitelist registration at package install
  - Interface compliance checking
  - Prevent arbitrary reflection/container resolution
  - Handler versioning

- [ ] **Input Validation System**
  - JSON Schema validator for mutation inputs
  - DTO validation via `spatie/laravel-data`
  - Standard error responses

#### Day 3: Enforcement Pipelines + PolicyEngine v0
- [ ] **PolicyEngine v0 (Minimal)**
  - `PolicyEngine::canAccessPage(User $user, array $allowedRoles): bool`
  - `PolicyEngine::check(User $user, string $policyName): bool`
  - Role checking
  - Rate limit hooks (call RateLimiter)
  - Scope resolution (delegates to ScopeEngine)

- [ ] **Query Execution Pipeline**
  - `QueryRouter::execute()` with 8-step enforcement
  - Authenticate → Page Access Check → Policy → Scope → Execute → Mask → Audit → Response

- [ ] **Mutation Execution Pipeline**
  - `MutationRouter::execute()` with 10-step enforcement
  - Authenticate → CSRF → Rate Limit → Policy → Validate → Transaction → Execute → Audit → Commit → Response

- [ ] **ScopeEngine Implementation**
  - Scope types: `global`, `campus`, `teacher_of_record`, `self_only`, `district`
  - Server-side query modification (never trust UI)
  - `hasAnyAccess()` for page-level precheck

- [ ] **ProjectionEngine (Field Masking)**
  - Server-side field hiding/masking
  - Applied after handler, before response
  - Respect `style: "masked"` and `secret: true` flags
  - **NEVER return raw values for `dataClass: "secret"`** (token only)

#### Day 3 (cont.): Data Classification & Audit
- [ ] **Define Data Classification Levels**
  - `public`, `internal`, `confidential`, `regulated`, `secret`
  - Storage rules per classification
  - Encryption requirements

- [ ] **Audit Event Taxonomy**
  - Standard naming: `package.<pkg>.query.<name>`, `sensitive.<action>.revealed`
  - Required metadata: actor_id, tenant_id, campus_id, target_type, target_id, reason, ip, user_agent, correlation_id
  - Create `package_audit_log` table with proper indexes

**Sprint 0 Deliverables**:
- ✅ Package Runtime Contract documented
- ✅ Handler Registry + required interfaces
- ✅ Enforcement pipelines (QueryRouter, MutationRouter, ScopeEngine, ProjectionEngine)
- ✅ Data classification rules
- ✅ Audit event taxonomy
- ✅ Database schema for audit log

**Success Criteria**:
- [ ] Can validate a package JSON against schema
- [ ] Can register handlers and verify interfaces
- [ ] Can apply scope filters to queries
- [ ] Can mask fields in responses
- [ ] Can log standardized audit events

---

### Sprint 1: Foundation & Schema (Week 1-2)
**Goal**: Establish JSON structure and basic rendering

#### Week 1: Schema & Validation
- [ ] **Day 1-2**: Define Layer 3 JSON schema (TypeScript definitions + JSON Schema)
  - Create schema files in `database/layer3-schema.json`
  - Document all component types, query formats, policy structures
  - Build JSON validator class (`src/PackageValidator.php`)

- [ ] **Day 3-4**: Build Component Registry
  - Create `src/Package/ComponentRegistry.php`
  - Register built-in component types (table, form, detail)
  - Component interface: `render(array $definition, array $context): string`

- [ ] **Day 5**: Page Router (Catch-All Strategy)
  - **DO NOT dynamically register routes** (breaks Laravel route caching)
  - Create single catch-all route: `GET /p/{packageId}/{pageId?}`
  - Create `app/Http/Controllers/Package/PageController.php`
  - Resolve page definitions from JSON at runtime
  - Optional: Register alias routes for friendly URLs (but still route through PageController)

#### Week 2: Table Component (Proof of Concept)
- [ ] **Day 1-3**: Generic Table Renderer
  - `src/Package/Components/TableComponent.php`
  - Parse columns, filters, pagination from JSON
  - Generate HTML with Bootstrap 5 styling
  - Client-side search/sort/filter JavaScript

- [ ] **Day 4**: Query Handler Router
  - `app/Http/Controllers/Package/QueryController.php`
  - Route queries to package handlers
  - Standard response format: `{ data: [], total: int, page: int }`

- [ ] **Day 5**: Integration Test
  - Create test package: `com.test.simple-table`
  - Render table from JSON
  - Verify query routing works
  - Document learnings

---

### Sprint 2: Security Layer (Week 3-4)
**Goal**: Policy engine + audit system

#### Week 3: Policy Engine v1 (Advanced Features)
- [ ] **Day 1-2**: Field-Level Policy Rules
  - `PolicyEngine::getVisibleFields(User $user, string $policyName): array`
  - Parse column-level `access` rules from JSON
  - Dynamic field projection based on role
  - Override for privileged actions

- [ ] **Day 3-4**: Advanced Conditions
  - `requiresMfa` enforcement
  - Time-based restrictions (business hours only)
  - Conditional scopes (e.g., "teacher OR campus admin")
  - Alert triggers (excessive use detection)

- [ ] **Day 5**: Policy Testing Suite
  - Unit tests for all scope types
  - Integration tests for policy violations
  - Test Student Directory reveal workflow end-to-end

#### Week 4: Audit & Rate Limiting
- [ ] **Day 1-2**: Enhanced Audit System
  - Extend `src/AuditLogger.php` for package events
  - Parse `auditEvents` from package JSON
  - Store: actor, target, action, reason, ip, timestamp

- [ ] **Day 3**: Rate Limiter
  - `app/Http/Middleware/PackageRateLimit.php`
  - Per-action rate limiting from policy JSON
  - Redis-backed (fallback to database)

- [ ] **Day 4-5**: Sensitive Action Pattern (Token-Based)
  - "Reveal Password" as reference implementation
  - **Mandatory pattern**: Mutation returns token → Client fetches secret via `/api/reveal/{token}`
  - Reason modal component
  - Time-limited token (30-60 seconds, stored in Cache)
  - Single-use consumption
  - Countdown timer UI (auto-hide after expiry)
  - Comprehensive logging (token, NOT secret)

---

### Sprint 3: Forms & Mutations (Week 5-6)
**Goal**: Complete CRUD workflows

#### Week 5: Form Component
- [ ] **Day 1-2**: Generic Form Renderer
  - Extend existing `DynamicFormRenderer.php`
  - Parse form JSON → HTML form
  - Client-side validation (Vanilla JS)

- [ ] **Day 3**: Mutation Handler Router
  - `app/Http/Controllers/Package/MutationController.php`
  - Route mutations to package handlers
  - Standard request/response format
  - CSRF verification

- [ ] **Day 4-5**: Form Actions Integration
  - Connect forms to mutations
  - Success/error handling
  - Redirect after save
  - Toast notifications

#### Week 6: Detail View & Dashboard
- [ ] **Day 1-2**: Detail Component
  - `src/Package/Components/DetailComponent.php`
  - Sectioned layout
  - Related data queries
  - Action buttons

- [ ] **Day 3-4**: Dashboard Components
  - Stat cards (KPI queries)
  - Action queues
  - Quick links
  - Charts (optional, using Chart.js)

- [ ] **Day 5**: Component Testing
  - Unit tests for all renderers
  - Integration tests for workflows
  - Security test suite (policies, audit, rate limits)

---

### Sprint 4: Student Directory Pilot (Week 7-8)
**Goal**: First real-world package implementation

#### Week 7: Backend Implementation
- [ ] **Day 1**: Design Student Directory package JSON
  - Pages: index (table) + detail
  - Queries: search, getById, grades.list
  - Mutations: revealPassword, export
  - Policies: teacher_of_record scope, reveal restrictions

- [ ] **Day 2-3**: Build Handler Classes
  - `app/Http/Controllers/StudentDirectory/StudentDirectoryController.php`
  - Implement all query methods
  - Implement mutation methods
  - Apply policies

- [ ] **Day 4**: Database Setup
  - Create student_password_reveals audit table
  - Seed test data (students, teachers, assignments)
  - Configure teacher-student relationships

- [ ] **Day 5**: Security Testing
  - Verify teachers only see assigned students
  - Test password reveal workflow
  - Validate audit logging
  - Check rate limiting

#### Week 8: Frontend Polish & Launch
- [ ] **Day 1-2**: UI Refinement
  - Style table to match mockup
  - Polish modal dialogs
  - Add loading states
  - Error handling UX

- [ ] **Day 3**: Mobile Responsiveness
  - Test on mobile devices
  - Responsive tables (card view on mobile)
  - Touch-friendly actions

- [ ] **Day 4**: Documentation
  - Package creator guide
  - JSON reference docs
  - Security guidelines
  - Migration instructions (Layer 2 → Layer 3)

- [ ] **Day 5**: Launch & Monitoring
  - Deploy to staging
  - User acceptance testing
  - Monitor audit logs
  - Performance benchmarking

---

## 🛠️ Technical Implementation Details

### File Structure
```
/var/www/woodson/thehub/
├── database/
│   ├── layer3-schema.json          # JSON Schema definition
│   └── migrations/
│       └── xxxx_add_package_audit_tables.php
├── src/
│   ├── Package/
│   │   ├── PackageValidator.php    # Validates Layer 3 JSON
│   │   ├── ComponentRegistry.php   # Registers component types
│   │   ├── PolicyEngine.php        # Enforces access policies
│   │   ├── QueryRouter.php         # Routes queries to handlers
│   │   ├── MutationRouter.php      # Routes mutations to handlers
│   │   └── Components/
│   │       ├── TableComponent.php
│   │       ├── FormComponent.php
│   │       ├── DetailComponent.php
│   │       └── DashboardComponent.php
│   └── PackageManager.php          # Extended for Layer 3
├── app/Http/
│   ├── Controllers/Package/
│   │   ├── QueryController.php
│   │   ├── MutationController.php
│   │   └── PageController.php
│   └── Middleware/
│       ├── PackageRouteResolver.php
│       ├── PackagePolicy.php
│       └── PackageRateLimit.php
├── public/assets/js/
│   ├── package-table.js            # Generic table client-side
│   ├── package-form.js             # Generic form client-side
│   └── sensitive-action.js         # Reveal workflow
└── resources/views/package/
    ├── table.blade.php
    ├── form.blade.php
    └── detail.blade.php
```

### Database Tables (New)
```sql
-- Package audit events (comprehensive logging)
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
);

-- Rate limiting storage
CREATE TABLE package_rate_limits (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_key VARCHAR(100) NOT NULL,
    hit_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    UNIQUE KEY unique_user_action_window (user_id, action_key, window_start),
    INDEX idx_expires (expires_at)
);

-- Sensitive action tokens (time-limited reveals)
CREATE TABLE sensitive_action_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    INDEX idx_token (token, expires_at),
    INDEX idx_user_action (user_id, action, created_at)
);

-- Export downloads (async job results)
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
);

-- Package metrics (performance tracking)
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
);

-- Package installations (with hash manifest for security)
ALTER TABLE section_installations ADD COLUMN IF NOT EXISTS package_hash VARCHAR(64) NULL AFTER installed_version;
ALTER TABLE section_installations ADD COLUMN IF NOT EXISTS publisher VARCHAR(100) NULL AFTER package_hash;
ALTER TABLE section_installations ADD COLUMN IF NOT EXISTS installed_by INT NULL AFTER publisher;
```

### Key Classes to Implement

#### 1. PackageValidator.php
```php
namespace Hub\Package;

class PackageValidator {
    private array $schema;

    public function validate(array $packageData): ValidationResult {
        // Validate against JSON Schema
        // Check component types exist
        // Verify query/mutation handlers are defined
        // Validate policy structure
        // Return detailed errors or success
    }
}
```

#### 2. ComponentRegistry.php
```php
namespace Hub\Package;

class ComponentRegistry {
    private static array $components = [];

    public static function register(string $type, string $className): void {
        self::$components[$type] = $className;
    }

    public static function render(string $type, array $definition, array $context): string {
        $componentClass = self::$components[$type] ?? null;
        if (!$componentClass) {
            throw new \Exception("Unknown component type: {$type}");
        }

        $component = new $componentClass();
        return $component->render($definition, $context);
    }
}
```

#### 3. PolicyEngine.php
```php
namespace Hub\Package;

class PolicyEngine {
    public function enforce(string $policyName, User $user, array $context): bool {
        $policy = $this->loadPolicy($policyName);

        // Check roles
        if (!$this->checkRoles($user, $policy['allowRoles'])) {
            return false;
        }

        // Check scope (teacher_of_record, campus, etc.)
        if (!$this->checkScope($user, $policy['scope'], $context)) {
            return false;
        }

        // Check rate limit
        if ($this->isRateLimited($user, $policyName, $policy['rateLimit'])) {
            return false;
        }

        return true;
    }

    private function checkScope(User $user, string $scope, array $context): bool {
        switch ($scope) {
            case 'teacher_of_record':
                return $this->isTeacherOfRecord($user, $context['student_id']);
            case 'campus':
                return $user->campus_id === $context['campus_id'];
            // ... more scope types
            default:
                return true;
        }
    }
}
```

#### 4. TableComponent.php
```php
namespace Hub\Package\Components;

class TableComponent implements ComponentInterface {
    public function render(array $definition, array $context): string {
        $query = $definition['query'];
        $pagination = $definition['pagination'] ?? ['pageSize' => 50];

        // Execute query via QueryRouter
        $data = QueryRouter::execute($query, [
            'page' => $context['page'] ?? 1,
            'pageSize' => $pagination['pageSize'],
            'filters' => $context['filters'] ?? []
        ]);

        // Render table HTML
        return view('package.table', [
            'columns' => $definition['columns'],
            'data' => $data['data'],
            'total' => $data['total'],
            'rowActions' => $definition['rowActions'] ?? [],
            'bulkActions' => $definition['bulkActions'] ?? []
        ])->render();
    }
}
```

---

## 🧪 Testing Strategy

### Unit Tests (80 tests minimum)
- [ ] JSON schema validation
- [ ] Component rendering (each type)
- [ ] Query routing
- [ ] Mutation routing
- [ ] Policy enforcement (each scope type)
- [ ] Rate limiting
- [ ] Audit logging
- [ ] Field masking

### Integration Tests (20 scenarios)
- [ ] Full table workflow (search, filter, paginate, action)
- [ ] Form submission → mutation → redirect
- [ ] Detail view rendering
- [ ] Sensitive action (reveal password end-to-end)
- [ ] Policy violations (403 responses)
- [ ] Rate limit exceeded (429 responses)
- [ ] Audit trail verification

### Security Tests (Critical)
- [ ] SQL injection attempts
- [ ] CSRF bypass attempts
- [ ] Rate limit bypass attempts
- [ ] Scope escalation attempts (teacher seeing other teachers' students)
- [ ] Field masking bypass attempts
- [ ] Audit log tampering attempts

---

## 🚨 Risk Assessment & Mitigation

### High Risk Areas

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Policy engine bugs allow unauthorized access | Critical | Medium | Exhaustive security testing, penetration testing, code review |
| Performance degradation on large datasets | High | High | Query optimization, caching strategy, pagination limits |
| JSON schema becomes too complex | Medium | High | Start simple, iterate, provide templates and generators |
| Package migration breaks existing features | High | Medium | Comprehensive regression testing, staged rollout |
| Audit logs grow too large | Medium | High | Automatic archiving, retention policies, separate database |

### Mitigation Strategies
1. **Security**: External security audit before production launch
2. **Performance**: Load testing with 10,000+ records, query profiling
3. **Complexity**: Developer documentation + examples + CLI generator
4. **Migration**: Feature flags, A/B testing, rollback plan
5. **Storage**: Automated log rotation, CloudWatch-style archiving

---

## 📈 Success Metrics

### Development Metrics
- [ ] All core components implemented and tested
- [ ] Student Directory pilot package complete
- [ ] Zero security vulnerabilities in audit
- [ ] 95%+ test coverage on policy engine
- [ ] Documentation complete and reviewed

### Performance Targets
- [ ] Table load time < 500ms (50 records)
- [ ] Form submission < 200ms
- [ ] Reveal password workflow < 1s end-to-end
- [ ] Rate limit check overhead < 10ms

### User Experience
- [ ] Teachers can access Student Directory easily
- [ ] Password reveal workflow is intuitive
- [ ] Mobile responsive on all components
- [ ] Zero confused support tickets about navigation

### Security Compliance
- [ ] 100% of sensitive actions logged
- [ ] Zero policy bypass incidents
- [ ] Rate limiting prevents abuse
- [ ] Field masking enforced universally

---

## 🎓 Training & Documentation

### Developer Documentation
1. **Package Creator Guide** - How to build a Layer 3 package
2. **JSON Reference** - Complete schema documentation
3. **Component Library** - All available component types with examples
4. **Security Guidelines** - Policy patterns, audit requirements
5. **Migration Guide** - Layer 2 → Layer 3 conversion

### Admin Documentation
1. **Package Installation** - How to install Layer 3 packages
2. **Policy Configuration** - Customizing access rules
3. **Audit Log Review** - Finding security events
4. **Troubleshooting** - Common issues and solutions

### End User Documentation
1. **Student Directory User Guide** - For teachers
2. **Sensitive Data Handling** - Password reveal workflow
3. **Privacy Policy** - What gets logged and why

---

## 🚀 Go/No-Go Criteria

### Ready for Beta (Week 8)
- ✅ Core components functional
- ✅ Student Directory pilot working
- ✅ Security audit passed
- ✅ Performance benchmarks met
- ✅ Documentation complete

### Ready for Production (Week 10)
- ✅ Beta testing feedback incorporated
- ✅ All critical bugs resolved
- ✅ Load testing passed (1000+ concurrent users)
- ✅ Backup/restore tested
- ✅ Monitoring and alerts configured
- ✅ Training materials delivered

---

## 🔄 Post-Launch (Week 11+)

### Immediate (Week 11-12)
- [ ] Monitor audit logs daily
- [ ] Track performance metrics
- [ ] Gather user feedback
- [ ] Address critical bugs within 24h

### Short-term (Month 2-3)
- [ ] Migrate 2-3 more packages to Layer 3
- [ ] Build package generator CLI tool
- [ ] Create visual package builder (optional)
- [ ] Optimize query performance

### Long-term (Month 4-6)
- [ ] All packages migrated to Layer 3
- [ ] Community package marketplace (optional)
- [ ] Advanced dashboard widgets
- [ ] Machine learning-powered policies (optional)

---

## 💰 Resource Requirements

### Development Team
- 2 Senior Full-Stack Developers (PHP + JavaScript)
- 1 Security Specialist (contract, 2 weeks)
- 1 Technical Writer (part-time, 2 weeks)

### Infrastructure
- Staging environment (clone of production)
- Load testing environment
- Redis cache (for rate limiting)
- CloudWatch or similar (audit log archiving)

### Budget Estimate
- Development: 200-250 hours @ $X/hour
- Security audit: 40 hours @ $Y/hour
- Infrastructure: $Z/month
- **Total**: Estimate based on your rates

---

## 📞 Stakeholder Communication

### Weekly Status Reports
- Sprint progress (% complete)
- Blockers and risks
- Decisions needed
- Demo of working features

### Key Milestones
1. **Week 2**: Table component demo
2. **Week 4**: Security layer demo (password reveal)
3. **Week 6**: Complete form workflow demo
4. **Week 8**: Student Directory beta launch
5. **Week 10**: Production ready review

---

## ✅ Definition of Done

A sprint is complete when:
- [ ] All code committed to version control
- [ ] Unit tests written and passing (95%+ coverage)
- [ ] Integration tests passing
- [ ] Code reviewed by peer
- [ ] Documentation updated
- [ ] Demo prepared for stakeholders
- [ ] No critical bugs outstanding

---

**Ready to start? Pick Sprint 1, Week 1, Day 1 and GO! 🚀**

---

## 📦 Recommended Laravel Packages

### Core Dependencies (Install These)

**Security & Permissions**
```bash
composer require spatie/laravel-permission
# OR use Laravel's native Policies/Gates (recommended for simplicity)
```

**Audit / Activity Logging**
```bash
composer require spatie/laravel-activitylog
# Alternative: owen-it/laravel-auditing (more model-change oriented)
```

**Query Building + Filters**
```bash
composer require spatie/laravel-query-builder
# Standardizes filtering, sorting, pagination from query params
```

**Schema / DTO Validation**
```bash
composer require spatie/laravel-data
# DTOs + validation (strongly recommended)

composer require opis/json-schema
# OR justinrainbow/json-schema
# For validating package JSON against schema
```

**Exports**
```bash
composer require maatwebsite/excel
# Excel/CSV exports

composer require barryvdh/laravel-dompdf
# PDF exports
```

**Rate Limiting**
- Use Laravel's built-in Rate Limiter (native, cache-backed)
- Requires Redis for production (or file cache for dev)

**Observability (Development)**
```bash
composer require laravel/telescope --dev
# Request/query/job debugging

composer require laravel/horizon
# Queue monitoring and metrics

composer require laravel/pulse
# NEW: Performance metrics and health monitoring
```

**UI Helper (Optional)**
- `alpinejs` (CDN or npm): Lightweight interactivity without full SPA
- Table grid: Tabulator.js (MIT) or DataTables (free tier)
- Consider: Build simple Blade components first, upgrade to Alpine/Livewire later

### Recommended Stack for Sprint 0-1
```bash
# Minimal viable dependencies
composer require spatie/laravel-query-builder
composer require spatie/laravel-data
composer require opis/json-schema
composer require spatie/laravel-activitylog
composer require maatwebsite/excel

# Development tools
composer require laravel/telescope --dev
```

### Alternative: Livewire for Component Runtime

If you want to reduce custom JS:
```bash
composer require livewire/livewire
```

**Pros**:
- Reactive tables/forms without writing JS
- JSON DSL maps to Livewire component configs
- Laravel-native, well-documented

**Cons**:
- Still need policy engine, handler registry, signing
- Tighter coupling to Laravel stack (fine if Hub is Laravel-first)

### Alternative: Inertia for SPA-like UX

```bash
composer require inertiajs/inertia-laravel
npm install @inertiajs/vue3
# OR @inertiajs/react
```

**Pros**:
- Best UX for "mini apps"
- Component-driven UI

**Cons**:
- Higher frontend complexity
- Build step required
- Still need all backend platform contract work

---

## Appendix: Quick Reference

### JSON Schema Cheat Sheet
```json
{
  "pages": [{ "route": "/path", "components": [...] }],
  "queries": { "name": { "handler": "Class@method" } },
  "mutations": { "name": { "handler": "Class@method", "policy": "name" } },
  "policies": { "name": { "allowRoles": [...], "scope": "..." } }
}
```

### Component Types
- `table` - Data grid
- `form` - Input collection
- `detail` - Single record
- `dashboard` - KPIs + queues
- `wizard` - Multi-step
- `report` - Export-ready

### Policy Scopes
- `global` - All records
- `campus` - User's campus only
- `teacher_of_record` - Assigned students only
- `self_only` - Own records only

### Rate Limit Formats
- `5/min` - 5 per minute
- `100/hour` - 100 per hour
- `1000/day` - 1000 per day

---

**Document Version**: 1.0
**Last Updated**: February 11, 2026
**Next Review**: After Sprint 1 completion
