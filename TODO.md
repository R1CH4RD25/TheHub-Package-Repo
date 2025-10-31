# The Hub - TODO List

**Last Updated:** October 31, 2025  
**Branch:** v1.3  
**Current Phase:** Test Suite Stabilization

---

## 🔴 Priority 1: Complete Test Error Fixes (37 remaining)

### Authentication Tests (26 errors) - HIGHEST PRIORITY
- [ ] Fix AuthLoginSecurityTest (17 errors)
- [ ] Fix AuthSectionSecurityTest (9 errors)

### Module Integration Tests
- [ ] Fix WorkflowRendererIntegrationTest (13 errors)
- [ ] Fix SectionIntegrationTest (13 errors)
- [ ] Fix CalendarRendererTest (10 errors)
- [ ] Fix KanbanRendererTest (9 errors)
- [ ] Fix InvitationIntegrationTest (9 errors)
- [ ] Fix FileManagerRendererTest (7 errors)

### Target: < 10 errors, < 10 failures

---

## 🟡 Priority 2: Boost Auth Coverage

### Current: Auth 20.88% lines → Target: 70%+

- [ ] Test login flow variations
- [ ] Test role checking logic
- [ ] Test permission validation
- [ ] Test session management
- [ ] Test OAuth integration
- [ ] Test security features
- [ ] Test edge cases

---

## 🟢 Priority 3: Test Coverage Expansion

### Untested Modules (0% coverage)
- [ ] ActionRenderer
- [ ] ComputationRenderer
- [ ] EmailNotificationRenderer
- [ ] PDFGeneratorRenderer

### API Integration Tests (8 untested endpoints)
- [ ] bullying-reports.php
- [ ] package-alerts.php
- [ ] package-discovery.php
- [ ] packages.php
- [ ] export.php
- [ ] upload-branding.php
- [ ] role-management.php
- [ ] system-config.php

---

## ✅ Completed (October 31, 2025)

- [x] Fixed TestDatabase::createTestUser() signature
- [x] Fixed SectionPermissionsTest helper methods
- [x] Fixed interface naming (ModuleRendererInterface → ModuleInterface)
- [x] Fixed handle() method signatures
- [x] Created header-modern.css
- [x] Reduced errors: 86 → 37 (-57%)
- [x] Increased assertions: 1,075 → 1,225 (+14%)
- [x] Improved coverage: 39.88% → 44.38%

---

**Status:** �� In Progress  
**Next:** Fix remaining 37 test errors  
**ETA:** 10-12 hours to stable

See [CHECKPOINT_2025-10-31.md](CHECKPOINT_2025-10-31.md) for details.
