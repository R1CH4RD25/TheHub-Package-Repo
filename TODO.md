# The Hub - TODO List
*Last Updated: October 29, 2025*

## 🔴 High Priority

### Add Parent Role to System
**Status**: Not Started  
**Assigned**: Unassigned  
**Description**: Add "parent" role to the role system to complete the user role hierarchy.

**Tasks**:
- [ ] Add `parent` to role enum/constants
- [ ] Create CSS badge styling for `.role-badge-parent`
  - Suggested colors: Background: `#dbeafe`, Text: `#1e40af` (light blue theme)
- [ ] Update role validation in Auth.php
- [ ] Add to role management dropdown in User Management tab
- [ ] Update role documentation in ROLES_DOCUMENTATION.md
- [ ] Test role assignment and permissions

**Files to Modify**:
- `src/Auth.php` - Role validation
- `public/assets/css/admin-modern.css` - Badge styling
- `public/admin/index.php` - Role management UI
- `docs/ROLES_DOCUMENTATION.md` - Documentation

**Depends On**: None  
**Blocks**: Role management system completion

---

### Integrate System Roles with Add Role Button
**Status**: Not Started  
**Assigned**: Unassigned  
**Description**: The "Add Role" button in User Management needs to be connected to the actual system roles instead of arbitrary role creation.

**Tasks**:
- [ ] Review current "Add Role" functionality
- [ ] Update to use defined system roles list:
  - staff, maintenance, maintenance_director, manager, admin, super_admin
  - teacher, counselor, principal, superintendent, secretary, librarian, it_support
  - parent (after adding)
- [ ] Add role descriptions/tooltips
- [ ] Implement role permission validation
- [ ] Add role change audit logging
- [ ] Test role assignment workflow

**Files to Modify**:
- `public/admin/index.php` - User Management tab
- `public/assets/js/admin.js` - Role management logic
- `public/api/users.php` - Role update endpoint

**Depends On**: Parent role addition  
**Blocks**: Complete user management system

---

## 🟡 Medium Priority

### CSS Cleanup - admin-modern.css
**Status**: Not Started  
**Assigned**: Unassigned  
**Description**: The admin-modern.css file has accumulated many appended sections during development and needs reorganization.

**Tasks**:
- [ ] Review all CSS rules in admin-modern.css
- [ ] Consolidate duplicate z-index declarations
- [ ] Remove conflicting or obsolete rules
- [ ] Organize into logical sections:
  - Layout & Grid
  - Sidebar & Navigation
  - Dropdown Menus
  - Forms & Inputs
  - Tables & Data Display
  - Modals & Overlays
  - Responsive Design
  - Animations & Transitions
- [ ] Add section comments
- [ ] Consider splitting into multiple focused files
- [ ] Test across all admin pages after changes

**Files to Modify**:
- `public/assets/css/admin-modern.css`

**Depends On**: None  
**Blocks**: None (but improves maintainability)

---

### Profile Page Enhancements
**Status**: Not Started  
**Assigned**: Unassigned  
**Description**: Consider additional fields for the user profile page beyond basic contact info.

**Tasks**:
- [ ] Gather requirements for additional fields:
  - Emergency contact information?
  - Department/Building assignment?
  - Profile photo upload (beyond Google avatar)?
  - Job title/position?
  - Bio/description?
- [ ] Design database schema changes if needed
- [ ] Update Profile tab UI
- [ ] Update API endpoint
- [ ] Add validation
- [ ] Test with different user roles

**Files to Modify**:
- `database/schema.sql` - If new columns needed
- `public/profile.php` - UI updates
- `public/api/profile.php` - API updates

**Depends On**: None  
**Blocks**: None

---

### Section Configuration Testing
**Status**: Not Started  
**Assigned**: Unassigned  
**Description**: Thoroughly test all section configuration features now that the tab is fixed.

**Tasks**:
- [ ] Test section detail loading for all sections
- [ ] Test category assignment
- [ ] Test permission configuration (submission & review)
- [ ] Test notification rule creation and editing
- [ ] Test guideline management (add, edit, delete, reorder)
- [ ] Test form configuration for sections with forms
- [ ] Verify validation works correctly
- [ ] Test with different user roles
- [ ] Check audit logging for all changes

**Files to Verify**:
- `public/admin/section-config-tab.php`
- `public/api/section-config.php`
- `public/assets/js/section-config.js` (if exists)

**Depends On**: None  
**Blocks**: Section system completion

---

## 🟢 Low Priority

### Collapsed Menu Tooltips
**Status**: Not Started  
**Description**: Add tooltips showing submenu items when hovering over collapsed menu groups.

**Tasks**:
- [ ] Design tooltip UI
- [ ] Add hover event listeners
- [ ] Position tooltips correctly
- [ ] Test accessibility (keyboard navigation)

---

### Keyboard Shortcuts
**Status**: Not Started  
**Description**: Add keyboard shortcuts for common admin actions.

**Suggested Shortcuts**:
- [ ] Ctrl+S - Save current form
- [ ] Ctrl+K - Open search/command palette
- [ ] Ctrl+/ - Show keyboard shortcuts help
- [ ] Esc - Close modals/dropdowns

---

### Recently Viewed Sections
**Status**: Not Started  
**Description**: Add a "Recently Viewed" or "Favorites" quick access section in the sidebar.

**Tasks**:
- [ ] Design UI component
- [ ] Store recent items in localStorage
- [ ] Add click handlers
- [ ] Limit to 5-10 most recent items

---

### Mobile Dropdown Testing
**Status**: Not Started  
**Description**: Test the new user profile dropdown on mobile devices.

**Tasks**:
- [ ] Test on iOS Safari
- [ ] Test on Android Chrome
- [ ] Test on tablets
- [ ] Verify touch interactions
- [ ] Check z-index layering on mobile

---

### Menu Animation Improvements
**Status**: Not Started  
**Description**: Add smooth transitions for menu group collapse/expand.

**Tasks**:
- [ ] Add CSS transitions
- [ ] Test animation performance
- [ ] Add easing functions
- [ ] Ensure accessibility (reduced motion preference)

---

## 🔧 Technical Debt

### Z-Index Audit
**Status**: Partially Complete  
**Description**: Review and standardize z-index values across all CSS files.

**Completed**:
- ✅ Fixed navbar overflow issue
- ✅ Set sensible values for user dropdown (10002)
- ✅ Documented z-index hierarchy

**Remaining**:
- [ ] Audit all z-index values in all CSS files
- [ ] Create centralized z-index variable system
- [ ] Document final hierarchy
- [ ] Remove extreme values (9999999 in hub.css tooltip)

**Files to Review**:
- `public/assets/css/admin.css`
- `public/assets/css/admin-modern.css`
- `public/assets/css/header.css`
- `public/assets/css/hub.css`
- `public/assets/css/style.css`
- All other CSS files

---

### Icon System Standardization
**Status**: Partially Complete  
**Description**: Standardize icon usage across the application.

**Completed**:
- ✅ Added FontAwesome 6.5.1
- ✅ Replaced emojis in sidebar with FontAwesome
- ✅ Fixed Bootstrap Icons rendering in section config

**Remaining**:
- [ ] Create icon usage guidelines
- [ ] Replace remaining emojis with icons (except status indicators)
- [ ] Ensure consistent sizing across all icons
- [ ] Document icon library usage (when to use FA vs BI)

---

## 📚 Documentation Needs

### Role System Documentation
**Status**: Needs Update  
- [ ] Update ROLES_DOCUMENTATION.md with parent role
- [ ] Document role permission matrix
- [ ] Add role change workflow documentation

### API Documentation
**Status**: Needs Creation  
- [ ] Document all API endpoints
- [ ] Add request/response examples
- [ ] Document authentication requirements
- [ ] Add error code reference

### User Guide
**Status**: Needs Creation  
- [ ] Create end-user documentation
- [ ] Screenshot each major feature
- [ ] Write step-by-step guides
- [ ] Create video tutorials (optional)

---

## ✅ Recently Completed (October 29, 2025)

- ✅ Collapsible sidebar menu groups with accordion behavior
- ✅ User profile dropdown in header
- ✅ Profile page with contact preferences
- ✅ FontAwesome integration across admin UI
- ✅ Section Configuration tab fixes (API and rendering)
- ✅ CSS color consistency for nav-user-name
- ✅ Z-index hierarchy fixes for dropdown visibility
- ✅ Bootstrap Icons rendering in section config cards
- ✅ Automatic section config loading on tab switch/refresh
- ✅ Menu organization improvements (Management → Sections)

---

## 🎯 Sprint Planning

### Sprint 1 (Next Session)
**Goal**: Complete role system and basic cleanup

1. Add Parent role to system
2. Integrate system roles with Add Role button
3. Begin CSS cleanup (admin-modern.css)
4. Test section configuration features

**Estimated Time**: 4-6 hours

### Sprint 2
**Goal**: Testing and polish

1. Complete CSS cleanup
2. Mobile responsive testing
3. Profile page enhancements
4. Documentation updates

**Estimated Time**: 3-4 hours

### Sprint 3
**Goal**: Quality of life features

1. Add keyboard shortcuts
2. Recently viewed sections
3. Menu animation improvements
4. Create user guide

**Estimated Time**: 4-5 hours

---

## 📊 Progress Tracking

**Overall System Completion**: ~85%

- ✅ Core Infrastructure: 100%
- ✅ Authentication System: 100%
- ✅ Module System: 100%
- ✅ Theme System: 100%
- 🟡 Admin Dashboard: 90%
- 🟡 Role Management: 85%
- 🟡 Section System: 80%
- 🟡 User Profile: 75%
- 🔴 Documentation: 60%
- 🔴 Testing Coverage: 40%

---

*For detailed development notes, see [DEVELOPMENT_LOG_2025-10-29.md](DEVELOPMENT_LOG_2025-10-29.md)*
