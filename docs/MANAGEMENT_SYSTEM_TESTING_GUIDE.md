# Management System Testing Guide

**Version**: 1.0  
**Date**: November 18, 2025  
**Status**: Ready for QA Testing

---

## 🎯 Test Overview

This guide covers testing the Management System (formerly Command Center) after the complete theme integration refactor. All 400+ lines of inline styles have been converted to theme-aware CSS variables.

---

## ✅ Pre-Test Checklist

Before starting tests, verify:

- [ ] Production CSS rebuilt: `bash build-css.sh`
- [ ] File sizes correct:
  - `production.css`: 179K
  - `production.min.css`: 101K
- [ ] Management styles at line 7500 in production.css
- [ ] 62 `mgmt-*` classes present
- [ ] Database has test submissions (BR-2025-001, BR-2025-002, BR-2025-003)
- [ ] User has super_admin role (user ID 18384)
- [ ] Server running: `cd public && php -S localhost:8000`

---

## 🧪 Test Suite 1: Theme Integration

### Test 1.1: Verify CSS Variable Usage
**Goal**: Confirm no hardcoded colors remain

**Steps**:
```bash
# Search for hardcoded hex colors in Management CSS
grep -i "#[0-9a-f]\{6\}" public/assets/css/management.css

# Should return 0 results (or only in comments)
# If any found, theme integration failed
```

**Expected**: No hex colors found in actual CSS rules

**Status**: [ ] Pass [ ] Fail

---

### Test 1.2: Theme Color Propagation
**Goal**: Verify changing site theme affects Management pages

**Steps**:
1. Navigate to **Admin Dashboard** → **Site Settings** → **Colors**
2. Note current Primary Color (default: `#667eea`)
3. Change Primary Color to `#e74c3c` (red)
4. Click **Save Color Settings**
5. Wait for "Colors saved successfully!"
6. Navigate to **Management** → **Bullying Report**
7. Observe:
   - Header background color
   - "Apply Filters" button color
   - Status badges (pending/in-progress)
   - Priority badges (high/urgent)
   - DataTables header row

**Expected**: All primary color elements change to red immediately (no page refresh needed)

**Status**: [ ] Pass [ ] Fail

**Notes**: _______________________________________

---

### Test 1.3: Typography Changes
**Goal**: Verify font changes propagate

**Steps**:
1. Admin Dashboard → Site Settings → Typography
2. Change Font Family to "Georgia, serif"
3. Change Base Font Size to "18px"
4. Save settings
5. Navigate to Management pages
6. Verify all text uses new font and size

**Expected**: All text renders in Georgia at 18px base size

**Status**: [ ] Pass [ ] Fail

---

### Test 1.4: Spacing Consistency
**Goal**: Verify spacing uses CSS variables

**Steps**:
1. Open browser DevTools (F12)
2. Navigate to Management → Section Selector
3. Inspect `.mgmt-container` element
4. Check computed styles for padding/margin
5. Verify values match `--spacing-*` variables

**Expected**: All spacing uses CSS variable values, not hardcoded px

**Status**: [ ] Pass [ ] Fail

---

## 🖥️ Test Suite 2: Production Mode

### Test 2.1: Production CSS Loading
**Goal**: Verify production.min.css loads correctly

**Steps**:
1. Edit `.env` file: Add `CSS_PRODUCTION_MODE=true`
2. Restart PHP server: `cd public && php -S localhost:8000`
3. Navigate to Management pages
4. Open DevTools → Network tab → Filter CSS
5. Verify only `production.min.css` loads (not individual files)
6. Check file size: ~101K

**Expected**: Single production.min.css loaded, Management styles work correctly

**Status**: [ ] Pass [ ] Fail

---

### Test 2.2: Cache Busting
**Goal**: Verify CSS version parameter updates

**Steps**:
1. Note current production.min.css URL parameter (e.g., `?v=1763475080`)
2. Run: `bash build-css.sh`
3. Refresh Management page
4. Check CSS URL parameter changed
5. Verify new styles loaded

**Expected**: Version parameter increments, browser loads new CSS

**Status**: [ ] Pass [ ] Fail

---

## 📱 Test Suite 3: Mobile Responsive

### Test 3.1: Tablet View (768px)
**Goal**: Verify layout adapts at tablet breakpoint

**Steps**:
1. Open DevTools → Toggle device toolbar (Ctrl+Shift+M)
2. Set viewport to iPad (768x1024)
3. Navigate to Management → Section Selector
4. Verify:
   - Stats cards stack vertically
   - Table remains readable
   - Buttons accessible
   - No horizontal scrolling

**Expected**: All elements fit viewport, no layout breaks

**Status**: [ ] Pass [ ] Fail

---

### Test 3.2: Mobile View (480px)
**Goal**: Verify layout adapts at mobile breakpoint

**Steps**:
1. Set viewport to iPhone SE (375x667)
2. Navigate to Management → Section List
3. Verify:
   - Filters bar stacks vertically
   - Table scrolls horizontally (DataTables responsive)
   - Action buttons accessible
   - No text overflow
   - Back button visible

**Expected**: Functional on small screens, no cut-off content

**Status**: [ ] Pass [ ] Fail

---

### Test 3.3: Small Mobile (360px)
**Goal**: Test minimum supported viewport

**Steps**:
1. Set viewport to 360x640 (common Android)
2. Test all Management pages
3. Verify all buttons/inputs tappable
4. Check text readable (not too small)
5. Verify forms functional

**Expected**: Usable on smallest common phones

**Status**: [ ] Pass [ ] Fail

---

## 🎨 Test Suite 4: Branding Customization

### Test 4.1: Change Display Name
**Goal**: Verify dynamic branding updates

**Steps**:
1. Admin Dashboard → Site Settings → Command Center tab
2. Change Display Name from "Management" to "Administration"
3. Click Save
4. Navigate to Management pages
5. Verify name changed in:
   - Page title (`<title>` tag)
   - Header (`<h1>` text)
   - Navigation breadcrumbs
   - Module selector card

**Expected**: All references update to "Administration"

**Status**: [ ] Pass [ ] Fail

---

### Test 4.2: Change Icon
**Goal**: Verify icon customization works

**Steps**:
1. Admin Dashboard → Site Settings → Command Center tab
2. Change Icon from "bi-kanban" to "bi-gear-fill"
3. Click Save
4. Navigate to Management pages
5. Verify new icon appears in:
   - Section selector header
   - Module selector card
   - Navigation elements

**Expected**: Gear icon displays instead of kanban

**Status**: [ ] Pass [ ] Fail

---

### Test 4.3: Change Description
**Goal**: Verify description text updates

**Steps**:
1. Admin Dashboard → Site Settings → Command Center tab
2. Change Description to "Custom management portal"
3. Click Save
4. Navigate to Module Selector (`/modules.php`)
5. Check Management card description

**Expected**: New description text displays

**Status**: [ ] Pass [ ] Fail

---

## 🔍 Test Suite 5: Visual Regression

### Test 5.1: Section Selector Page
**Goal**: Verify styling matches design

**Navigation**: `/command/index.php`

**Check**:
- [ ] Header with icon and title properly styled
- [ ] Stats cards show correct metrics
- [ ] Urgent badge pulses (animation)
- [ ] Table headers have gradient background
- [ ] Hover states work on rows
- [ ] "View Details" button styled correctly
- [ ] Footer stays at bottom

**Status**: [ ] Pass [ ] Fail

**Screenshot**: _______________________________________

---

### Test 5.2: Section Dashboard (DataTables)
**Goal**: Verify list view styling

**Navigation**: `/command/section.php?section=bullying-report`

**Check**:
- [ ] Breadcrumb navigation visible
- [ ] Back button styled correctly
- [ ] Filter bar layout proper
- [ ] Apply/Clear buttons have icons
- [ ] DataTables header styled with theme colors
- [ ] Status badges colored correctly (pending=warning, in-progress=info, resolved=success)
- [ ] Priority badges styled (high=orange, urgent=red with pulse)
- [ ] Action buttons (View/Edit/Delete) accessible
- [ ] Pagination controls work
- [ ] Search box functional

**Status**: [ ] Pass [ ] Fail

**Screenshot**: _______________________________________

---

### Test 5.3: Submission Detail Page
**Goal**: Verify detail view styling

**Navigation**: `/command/submission.php?id=<submission_id>`

**Check**:
- [ ] Breadcrumb shows correct path
- [ ] Back button returns to list
- [ ] Tab navigation works (Details/Comments/Attachments/History)
- [ ] Status select dropdown styled
- [ ] Priority badges match design
- [ ] Metadata section properly laid out
- [ ] Timeline events color-coded
- [ ] Comment form styled consistently
- [ ] Attachment list readable
- [ ] History timeline formatted correctly

**Status**: [ ] Pass [ ] Fail

**Screenshot**: _______________________________________

---

## ⚠️ Test Suite 6: Error Handling

### Test 6.1: Missing CSS Variables
**Goal**: Verify fallback values work

**Steps**:
1. Temporarily comment out CSS variables in `public/api/theme-css.php`
2. Reload Management page
3. Verify fallback colors display (page doesn't break)
4. Restore CSS variables

**Expected**: Page renders with default colors from fallbacks

**Status**: [ ] Pass [ ] Fail

---

### Test 6.2: Invalid Theme Settings
**Goal**: Verify error handling for bad theme values

**Steps**:
1. Admin Dashboard → Site Settings → Colors
2. Enter invalid color: "not-a-color"
3. Try to save
4. Verify validation prevents save

**Expected**: Error message displayed, invalid value rejected

**Status**: [ ] Pass [ ] Fail

---

## 🚀 Test Suite 7: Performance

### Test 7.1: CSS File Size
**Goal**: Verify production CSS optimized

**Steps**:
```bash
# Check file sizes
ls -lh public/assets/css/production.css
ls -lh public/assets/css/production.min.css

# Calculate compression ratio
echo "Compression: $((100 - ($(stat -c%s public/assets/css/production.min.css) * 100 / $(stat -c%s public/assets/css/production.css))))%"
```

**Expected**: 
- production.css: ~179K
- production.min.css: ~101K
- Compression ratio: ~43%

**Status**: [ ] Pass [ ] Fail

---

### Test 7.2: Page Load Speed
**Goal**: Verify no performance regression

**Steps**:
1. Open DevTools → Network tab
2. Hard refresh Management page (Ctrl+Shift+R)
3. Check:
   - CSS load time < 50ms
   - Total page load < 1s
   - No 404 errors for CSS files

**Expected**: Fast load times, no errors

**Status**: [ ] Pass [ ] Fail

---

### Test 7.3: DataTables Performance
**Goal**: Verify large datasets handle well

**Steps**:
1. Create 100+ test submissions
2. Navigate to section dashboard
3. Verify:
   - Table loads quickly
   - Pagination works smoothly
   - Search filters fast
   - No UI lag

**Expected**: Smooth performance with large datasets

**Status**: [ ] Pass [ ] Fail

---

## 🐛 Known Issues Log

Document any bugs found during testing:

| Test # | Issue | Severity | Notes |
|--------|-------|----------|-------|
| | | High/Med/Low | |

---

## 📊 Test Results Summary

**Date**: _______________  
**Tester**: _______________  
**Environment**: Dev / Staging / Production

| Suite | Tests | Pass | Fail | Skip |
|-------|-------|------|------|------|
| 1. Theme Integration | 4 | | | |
| 2. Production Mode | 2 | | | |
| 3. Mobile Responsive | 3 | | | |
| 4. Branding | 3 | | | |
| 5. Visual Regression | 3 | | | |
| 6. Error Handling | 2 | | | |
| 7. Performance | 3 | | | |
| **TOTAL** | **20** | | | |

**Pass Rate**: _____%

---

## ✅ Sign-Off

### Developer
- [ ] All tests passing
- [ ] No known critical bugs
- [ ] Documentation updated
- [ ] Ready for QA

**Signature**: _______________  
**Date**: _______________

### QA Tester
- [ ] All tests executed
- [ ] Issues logged
- [ ] Screenshots captured
- [ ] Ready for production

**Signature**: _______________  
**Date**: _______________

### Product Owner
- [ ] Functionality approved
- [ ] Design approved
- [ ] Performance acceptable
- [ ] Ready for release

**Signature**: _______________  
**Date**: _______________

---

## 📚 Additional Resources

- **Architecture**: `docs/COMMAND_CENTER_ARCHITECTURE.md`
- **Theme Guidelines**: `docs/PACKAGE_THEME_GUIDELINES.md`
- **Quick Reference**: `docs/THEME_VARIABLES_QUICK_REF.md`
- **Production CSS**: `public/assets/css/production.min.css`
- **Management CSS**: `public/assets/css/management.css`

---

**Last Updated**: November 18, 2025  
**Version**: 1.0  
**Status**: 🟢 Ready for Testing
