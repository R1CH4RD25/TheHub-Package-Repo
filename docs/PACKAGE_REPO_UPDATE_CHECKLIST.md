# GitHub Package Repository Update Checklist

**Repository:** https://github.com/R1CH4RD25/TheHub-Package-Repo
**Date:** February 11, 2026
**Trigger:** Auditor feedback on Layer 2 compliance gaps in package specification
**Impact:** All packages with `hub_cards` that create user submissions

---

## 🎯 Overview

The auditor identified that our package specification (and example packages) had **UI separation** (Hub cards + Management sections) but lacked the **formal enforcement blocks** required to make Layer 2 compliance provable and validator-enforceable.

**Goal:** Update TheHub-Package-Repo to require Layer 2 compliance for all packages with user submissions.

---

## 📋 Required Updates

### 1. Core Specification Document

**File:** `PACKAGE_SPECIFICATION.md`

- [ ] **Add Section:** "Layer 2 Compliance Requirements"
  - [ ] Explain when Layer 2 is required (hub_cards with user submissions)
  - [ ] List the 5 required blocks: entities, workflow_states, manager_actions, audit_events, permissions
  - [ ] Add compliance matrix showing which package types need which blocks

- [ ] **Update Section:** "Package Metadata"
  - [ ] Add `capabilities` array with `"layer2-compliant"` option
  - [ ] Update example to show Layer 2 capability flag

- [ ] **Add Section:** "`entities` Block Specification"
  ```json
  "entities": [
    {
      "id": "string (unique identifier)",
      "label": "string (human-readable)",
      "table": "string (database table name)",
      "primary_key": "string (usually 'id')",
      "owner_field": "string (tracks who created record)",
      "status_field": "string (current workflow state)",
      "workflow": "string (references workflow_states.id)",
      "description": "string (optional)"
    }
  ]
  ```
  - [ ] Add validation rules for each field
  - [ ] Explain entity-to-workflow relationship

- [ ] **Add Section:** "`workflow_states` Block Specification"
  ```json
  "workflow_states": [
    {
      "id": "string (unique workflow identifier)",
      "entity": "string (references entities.id)",
      "states": [
        {
          "id": "string (state name, UPPERCASE)",
          "label": "string (display name)",
          "description": "string (optional)",
          "terminal": "boolean (is final state?)"
        }
      ],
      "transitions": [
        {
          "from": "string (source state id)",
          "to": "string (target state id)",
          "actor": "string (user|manager|admin)"
        }
      ],
      "default_state": "string (initial state for new records)"
    }
  ]
  ```
  - [ ] Add validation rules (no orphaned states in transitions)
  - [ ] Explain state machine lifecycle

- [ ] **Add Section:** "`manager_actions` Block Specification"
  ```json
  "manager_actions": {
    "entity_id": {
      "editable_fields": [
        {
          "field": "string (database column name)",
          "label": "string (UI label)",
          "allowed_states": ["array of state ids"],
          "requires_reason": "boolean (must manager explain change?)",
          "validation": {
            "type": "string (number|text|foreign_key|etc)",
            "min": "number (optional)",
            "max": "number (optional)",
            "pattern": "string (regex, optional)"
          }
        }
      ],
      "immutable_fields": ["array of field names that can NEVER be edited"]
    }
  }
  ```
  - [ ] Explain field-level edit boundaries
  - [ ] Document validation schema options
  - [ ] Emphasize immutable_fields enforcement

- [ ] **Add Section:** "`audit_events` Block Specification"
  ```json
  "audit_events": {
    "events": [
      {
        "event_type": "string (UPPERCASE_SNAKE_CASE)",
        "description": "string (human-readable)",
        "severity": "string (info|warning|error)",
        "required_fields": ["array of field names that MUST be logged"]
      }
    ],
    "audit_requirements": {
      "retention_days": "number|null (null = indefinite)",
      "immutable": "boolean (true = logs cannot be edited)",
      "package_log_table": "string (optional package-specific table)",
      "use_global_log": "boolean (MUST be true for Layer 2)"
    }
  }
  ```
  - [ ] **CRITICAL:** Document that `use_global_log: true` is MANDATORY for Layer 2
  - [ ] List recommended event types (SUBMITTED, REVIEWED, CORRECTED, APPROVED, REJECTED)
  - [ ] Explain required_fields enforcement

- [ ] **Update Section:** "`permissions` Block Specification"
  - [ ] Add `workflow_actions` array to role permission structure:
  ```json
  "permissions": {
    "role_id": {
      "description": "string",
      "capabilities": ["array of permission strings"],
      "workflow_actions": ["array of allowed workflow actions"]
    }
  }
  ```
  - [ ] Explain that at least one role must have non-empty `workflow_actions`
  - [ ] Remove old `capabilities_by_role` from manager_actions (if it exists)

- [ ] **Update Section:** "`hub_cards` Access Model"
  - [ ] Change examples from `"access": ["user"]` to package-specific roles
  - [ ] Add warning about ambiguous global role references
  - [ ] Show correct pattern: `"access": ["package_user", "package_manager", "package_admin"]`

- [ ] **Add Section:** "Layer 2 Compliance Matrix"
  ```
  Package Type                     | entities | workflow_states | manager_actions | audit_events | use_global_log
  -------------------------------- | -------- | --------------- | --------------- | ------------ | --------------
  Configuration-only (no hub_cards)| ❌       | ❌              | ❌              | ❌           | ❌
  Display-only (hub_cards, no forms)| ❌      | ❌              | ❌              | ❌           | ❌
  User submissions (hub_cards forms)| ✅      | ✅              | ✅              | ✅           | ✅ (required)
  ```

- [ ] **Add Section:** "Validation Enforcement"
  - [ ] Document that PackageValidator.php enforces Layer 2 compliance
  - [ ] List validation checks performed at install time
  - [ ] Explain install will fail if Layer 2 blocks missing/invalid

---

### 2. Package Template

**File:** `templates/package-template.hubpkg`

- [ ] **Update `hub_cards` access:**
  ```json
  "hub_cards": [
    {
      "id": "example-card",
      "access": ["pkg_user", "pkg_manager", "pkg_admin"],  // ✅ package-specific
      // NOT: "access": ["user"]  ❌
    }
  ]
  ```

- [ ] **Add `entities` block (commented with examples):**
  ```json
  "entities": [
    // {
    //   "id": "submission",
    //   "label": "User Submission",
    //   "table": "pkg_submissions",
    //   "primary_key": "id",
    //   "owner_field": "created_by_user_id",
    //   "status_field": "status",
    //   "workflow": "submission_workflow",
    //   "description": "User-submitted records requiring manager review"
    // }
  ],
  ```

- [ ] **Add `workflow_states` block (commented with examples):**
  ```json
  "workflow_states": [
    // {
    //   "id": "submission_workflow",
    //   "entity": "submission",
    //   "states": [
    //     {"id": "SUBMITTED", "label": "Submitted", "terminal": false},
    //     {"id": "IN_REVIEW", "label": "In Review", "terminal": false},
    //     {"id": "APPROVED", "label": "Approved", "terminal": true}
    //   ],
    //   "transitions": [
    //     {"from": "SUBMITTED", "to": "IN_REVIEW", "actor": "manager"},
    //     {"from": "IN_REVIEW", "to": "APPROVED", "actor": "manager"}
    //   ],
    //   "default_state": "SUBMITTED"
    // }
  ],
  ```

- [ ] **Add `manager_actions` block (commented with examples):**
  ```json
  "manager_actions": {
    // "submission": {
    //   "editable_fields": [
    //     {
    //       "field": "amount",
    //       "label": "Amount",
    //       "allowed_states": ["SUBMITTED", "IN_REVIEW"],
    //       "requires_reason": true,
    //       "validation": {"type": "number", "min": 0}
    //     }
    //   ],
    //   "immutable_fields": ["id", "created_at", "created_by_user_id"]
    // }
  },
  ```

- [ ] **Add `audit_events` block (commented with examples):**
  ```json
  "audit_events": {
    // "events": [
    //   {
    //     "event_type": "SUBMISSION_CREATED",
    //     "description": "User created submission",
    //     "severity": "info",
    //     "required_fields": ["user_id", "submission_id"]
    //   },
    //   {
    //     "event_type": "SUBMISSION_CORRECTED",
    //     "description": "Manager corrected submission data",
    //     "severity": "warning",
    //     "required_fields": ["manager_id", "submission_id", "field_name", "old_value", "new_value", "correction_reason"]
    //   }
    // ],
    // "audit_requirements": {
    //   "retention_days": null,
    //   "immutable": true,
    //   "package_log_table": "pkg_audit_logs",
    //   "use_global_log": true  // ✅ REQUIRED for Layer 2
    // }
  },
  ```

- [ ] **Update `permissions` block to include `workflow_actions`:**
  ```json
  "permissions": {
    "pkg_user": {
      "description": "Can submit records",
      "capabilities": ["pkg.create", "pkg.view_own"],
      "workflow_actions": []  // ✅ users cannot perform workflow actions
    },
    "pkg_manager": {
      "description": "Can review and correct submissions",
      "capabilities": ["pkg.create", "pkg.view_all", "pkg.edit"],
      "workflow_actions": ["review", "correct", "approve", "reject"]  // ✅ managers can
    }
  },
  ```

- [ ] **Add `"layer2-compliant"` to capabilities** (when applicable):
  ```json
  "capabilities": [
    "forms",
    "layer2-compliant"  // ✅ signals Layer 2 compliance
  ],
  ```

---

### 3. README Updates

**File:** `README.md`

- [ ] **Add Section:** "Layer 2 Compliance for User Submission Packages"
  - [ ] Explain what Layer 2 is (manager oversight with audit-grade traceability)
  - [ ] List the 5 required blocks for packages with user submissions
  - [ ] Link to `PACKAGE_SPECIFICATION.md` for detailed spec
  - [ ] Link to `docs/LAYER2_MIGRATION_GUIDE.md` for upgrade path

- [ ] **Update Section:** "Package Types"
  - [ ] **Configuration-only packages:** No hub_cards, just management sections (no Layer 2 required)
  - [ ] **Display-only packages:** Hub cards showing read-only data (no Layer 2 required)
  - [ ] **Layer 2 packages:** Hub cards with user submissions requiring manager review (Layer 2 REQUIRED)

- [ ] **Add Section:** "Package Development Checklist"
  ```markdown
  - [ ] Define package metadata and compatibility
  - [ ] Create hub_cards with package-specific role access
  - [ ] Create management_sections for manager UI
  - [ ] If package has user submissions (hub_cards with forms):
    - [ ] Add entities block (link workflows to tables)
    - [ ] Add workflow_states block (define submission lifecycle)
    - [ ] Add manager_actions block (editable vs immutable fields)
    - [ ] Add audit_events block (required logging taxonomy)
    - [ ] Set use_global_log: true in audit_requirements
    - [ ] Add workflow_actions to permissions block
    - [ ] Add "layer2-compliant" to capabilities
  - [ ] Define roles with package-specific IDs
  - [ ] Run PackageValidator before publishing
  - [ ] Test installation in dev environment
  ```

- [ ] **Update Section:** "Validation"
  - [ ] Document that packages MUST pass PackageValidator.php checks
  - [ ] Explain that Layer 2 compliance is enforced at install time
  - [ ] Show example validation failure messages

---

### 4. Create Migration Guide

**File:** `docs/LAYER2_MIGRATION_GUIDE.md`

- [ ] **Create comprehensive migration guide with:**

  - [ ] **Section 1: Overview**
    - [ ] What changed (why Layer 2 is now required)
    - [ ] Which packages need to migrate
    - [ ] Timeline/deprecation schedule

  - [ ] **Section 2: Step-by-Step Upgrade Path**
    - [ ] Step 1: Fix access model in hub_cards
    - [ ] Step 2: Add entities block
    - [ ] Step 3: Add workflow_states block
    - [ ] Step 4: Add manager_actions block
    - [ ] Step 5: Add audit_events block
    - [ ] Step 6: Update permissions with workflow_actions
    - [ ] Step 7: Update capabilities and version number
    - [ ] Step 8: Validate with PackageValidator

  - [ ] **Section 3: Before/After Examples**
    - [ ] Show complete diff for each block
    - [ ] Use vehicle-maintenance v2.0.0 → v2.1.0 as reference

  - [ ] **Section 4: Validation Error Reference**
    - [ ] List all PackageValidator Layer 2 error codes
    - [ ] Explain what each error means
    - [ ] Show resolution steps for each error

  - [ ] **Section 5: Testing Checklist**
    - [ ] How to run PackageValidator locally
    - [ ] How to test workflow transitions
    - [ ] How to verify audit logging
    - [ ] How to check manager edit boundaries

  - [ ] **Section 6: Common Mistakes**
    - [ ] Using `"access": ["user"]` instead of package roles
    - [ ] Forgetting `use_global_log: true`
    - [ ] Orphaned states in workflow transitions
    - [ ] Missing required_fields in audit events
    - [ ] Duplicate permissions in multiple locations

---

### 5. Update Example Packages

#### a) Vehicle Maintenance

**File:** `packages/operations/fleet/vehicle-maintenance-v2/vehicle-maintenance.hubpkg`

- [ ] **Replace with:** `/var/www/woodson/thehub/vehicle-maintenance_2.1.0.hubpkg`
- [ ] **Update version:** 2.0.0 → 2.1.0
- [ ] **Add CHANGELOG entry:**
  ```markdown
  ## [2.1.0] - 2026-02-11
  ### Added
  - Layer 2 compliance blocks (entities, workflow_states, manager_actions, audit_events)
  - Audit-grade manager oversight for fuel logs and maintenance events
  - Field-level edit boundaries with reason requirements
  - Formal workflow state machine (SUBMITTED → IN_REVIEW → CORRECTED → APPROVED/REJECTED)

  ### Changed
  - Fixed access model in hub_cards (now uses package-specific roles)
  - Consolidated permissions block (single source of truth)
  - Added vm_audit_logs to database tables

  ### Migration
  - Existing v2.0.0 installations should upgrade to v2.1.0 for audit compliance
  - See LAYER2_MIGRATION_GUIDE.md for upgrade steps
  ```

#### b) Bullying Report Package

**File:** `packages/student-services/bullying-report/bullying-report.hubpkg`

- [ ] **Add all Layer 2 blocks:**
  - [ ] `entities` block (bullying_report entity)
  - [ ] `workflow_states` block (SUBMITTED → ASSIGNED → IN_REVIEW → RESOLVED → CLOSED)
  - [ ] `manager_actions` block (editable fields: incident_date, location, involved_students; immutable: reporter_id, submitted_at)
  - [ ] `audit_events` block (REPORT_SUBMITTED, REPORT_ASSIGNED, REPORT_CORRECTED, REPORT_RESOLVED, REPORT_CLOSED)
  - [ ] Update `permissions` with `workflow_actions`

- [ ] **Fix access model** in hub_cards
- [ ] **Add** `"layer2-compliant"` capability
- [ ] **Update version** number
- [ ] **Update CHANGELOG**

#### c) Reimbursement Package

**File:** `packages/finance/reimbursement/reimbursement.hubpkg`

- [ ] **Add all Layer 2 blocks:**
  - [ ] `entities` block (reimbursement_request entity)
  - [ ] `workflow_states` block (SUBMITTED → IN_REVIEW → CORRECTED → APPROVED → PAID / REJECTED)
  - [ ] `manager_actions` block (editable fields: amount, expense_category, receipt_total; immutable: requester_id, submitted_at)
  - [ ] `audit_events` block (REQUEST_SUBMITTED, REQUEST_CORRECTED, REQUEST_APPROVED, REQUEST_REJECTED, REQUEST_PAID)
  - [ ] Update `permissions` with `workflow_actions`

- [ ] **Fix access model** in hub_cards
- [ ] **Add** `"layer2-compliant"` capability
- [ ] **Update version** number
- [ ] **Update CHANGELOG**

---

### 6. Create Validation Testing Script

**File:** `tools/validate-package.js` (or `.php`)

- [ ] **Create script that:**
  - [ ] Takes package file path as argument
  - [ ] Loads package JSON
  - [ ] Runs same validation as PackageValidator.php
  - [ ] Outputs colorized pass/fail results
  - [ ] Lists all errors with severity levels
  - [ ] Provides resolution hints for each error

- [ ] **Example usage:**
  ```bash
  node tools/validate-package.js packages/operations/fleet/vehicle-maintenance-v2/vehicle-maintenance.hubpkg
  ```

- [ ] **Output format:**
  ```
  ✅ Package Structure: PASS
  ✅ Layer 2 Compliance: PASS
  ✅ System Requirements: PASS
  ✅ Dependencies: PASS
  ✅ Conflicts: PASS

  ══════════════════════════════════════════
  RESULT: Package can be installed ✅
  ══════════════════════════════════════════
  ```

---

### 7. Update Package Repository CI/CD

**File:** `.github/workflows/validate-packages.yml`

- [ ] **Add GitHub Action that:**
  - [ ] Runs on every PR
  - [ ] Validates all changed `.hubpkg` files
  - [ ] Fails PR if Layer 2 compliance validation fails
  - [ ] Comments validation results on PR

- [ ] **Example workflow:**
  ```yaml
  name: Validate Packages
  on:
    pull_request:
      paths:
        - 'packages/**/*.hubpkg'

  jobs:
    validate:
      runs-on: ubuntu-latest
      steps:
        - uses: actions/checkout@v3
        - name: Validate changed packages
          run: |
            for file in $(git diff --name-only origin/main | grep '.hubpkg$'); do
              echo "Validating $file..."
              node tools/validate-package.js "$file"
            done
  ```

---

### 8. Documentation Cross-Links

- [ ] **Update TheHub-Package-Repo README:**
  - [ ] Add link to `TheHub/THE_HUB_CONCEPT_AND_ARCHITECTURE.md` (3-layer architecture explanation)
  - [ ] Add link to `TheHub/src/PackageValidator.php` (validation enforcement)

- [ ] **Update TheHub README:**
  - [ ] Add link to `TheHub-Package-Repo/PACKAGE_SPECIFICATION.md` (package development guide)
  - [ ] Add link to `TheHub-Package-Repo/docs/LAYER2_MIGRATION_GUIDE.md` (upgrade guide)

---

## 🧪 Testing Plan

Before merging to main branch:

- [ ] **Unit Test:** Run PackageValidator on all 3 example packages (vehicle-maintenance, bullying-report, reimbursement)
- [ ] **Integration Test:** Install each package in dev environment
- [ ] **Workflow Test:** Submit test records and verify state transitions
- [ ] **Audit Test:** Trigger manager corrections and verify audit_logs entries
- [ ] **Edit Boundary Test:** Attempt to edit immutable_fields and verify rejection
- [ ] **Validation Test:** Try to install non-compliant package and verify install failure

---

## 📅 Timeline

- [ ] **Week 1:** Update specification documents (PACKAGE_SPECIFICATION.md, README.md)
- [ ] **Week 2:** Create migration guide and update package template
- [ ] **Week 3:** Upgrade example packages (vehicle-maintenance, bullying-report, reimbursement)
- [ ] **Week 4:** Create validation script and CI/CD workflow
- [ ] **Week 5:** Testing and documentation review
- [ ] **Week 6:** Merge to main and announce to package developers

---

## 🚨 Breaking Changes Notice

**IMPORTANT:** This is a **BREAKING CHANGE** for packages with user submissions.

### Deprecation Schedule

- **Immediate:** New packages MUST be Layer 2 compliant
- **30 days:** Existing packages with hub_cards get warnings
- **60 days:** Non-compliant packages will fail installation
- **90 days:** Non-compliant packages removed from registry

### Communication Plan

- [ ] Email to all package developers
- [ ] Post announcement in community forum
- [ ] Update package repository homepage with migration banner
- [ ] Create video tutorial for Layer 2 migration

---

## ✅ Verification Checklist

Before closing this issue:

- [ ] All specification documents updated
- [ ] Package template includes Layer 2 blocks
- [ ] Migration guide complete with examples
- [ ] All 3 example packages upgraded and tested
- [ ] Validation script created and tested
- [ ] CI/CD workflow running successfully
- [ ] Documentation cross-links added
- [ ] Testing plan executed (all tests pass)
- [ ] Breaking changes announced
- [ ] Migration timeline published

---

## 📚 References

- **Auditor Feedback:** See original assessment (this conversation)
- **Architecture Doc:** `/var/www/woodson/thehub/THE_HUB_CONCEPT_AND_ARCHITECTURE.md`
- **Validator Code:** `/var/www/woodson/thehub/src/PackageValidator.php`
- **Example Package:** `/var/www/woodson/thehub/vehicle-maintenance_2.1.0.hubpkg`
- **Upgrade Doc:** `/var/www/woodson/thehub/VEHICLE_MAINTENANCE_LAYER2_UPGRADE.md`

---

## 🎯 Success Criteria

This checklist is complete when:

1. ✅ Package specification formally defines Layer 2 requirements
2. ✅ PackageValidator enforces Layer 2 compliance at install time
3. ✅ All example packages are Layer 2 compliant
4. ✅ Migration guide enables developers to upgrade existing packages
5. ✅ CI/CD prevents non-compliant packages from being merged
6. ✅ Declared controls (package JSON) match Enforced controls (validator)

**Result:** Audit-grade package ecosystem with mechanically enforceable manager oversight.
