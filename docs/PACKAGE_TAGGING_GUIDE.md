# Package Tagging System Guide

## 🏷️ Overview

The Hub now uses a **multi-dimensional tagging system** based on directory structure. Each level of the directory path becomes a searchable tag, allowing packages to belong to multiple categories simultaneously.

---

## 📁 Directory Structure = Tags

### **How It Works:**
```
packages/
├── student/safety/bullying-report.hubpkg          → Tags: ['student', 'safety']
├── staff/maintenance/work-order.hubpkg            → Tags: ['staff', 'maintenance']
├── parent/communication/newsletter.hubpkg         → Tags: ['parent', 'communication']
├── schoolwide/reporting/analytics-dashboard.hubpkg → Tags: ['schoolwide', 'reporting']
└── student/forms/lunch-order.hubpkg               → Tags: ['student', 'forms']
```

**Each directory segment becomes a tag!**
- First segment = Primary category (for backward compatibility)
- All segments = Searchable/filterable tags

---

## 🎯 Recommended Tag Dimensions

### **1. Audience Tags** (Who uses it)
- `student` 🎓 - Student-facing tools
- `staff` 👥 - Staff/employee tools
- `parent` 👨‍👩‍👧 - Parent/guardian tools
- `schoolwide` 🏫 - Entire school community
- `admin` 👔 - Administration only

### **2. Department Tags** (Which department)
- `maintenance` 🔧 - Facilities & maintenance
- `transportation` 🚌 - Bus routing, fuel tracking
- `counseling` 💚 - Counselor services
- `athletics` ⚽ - Sports & activities
- `nutrition` 🍎 - Cafeteria & food services
- `it` 💻 - Technology services
- `library` 📚 - Media center

### **3. Purpose Tags** (What it does)
- `safety` 🛡️ - Safety & security
- `reporting` 📋 - Data reporting & analytics
- `communication` 💬 - Notifications & messaging
- `forms` 📝 - Data collection forms
- `workflows` ⚙️ - Approval processes
- `scheduling` 📅 - Calendar & events
- `inventory` 📦 - Asset tracking

### **4. Data Type Tags** (What it manages)
- `incidents` 🚨 - Incident reporting
- `requests` 📤 - Service requests
- `surveys` 📊 - Feedback collection
- `records` 📂 - Record keeping
- `equipment` 🏷️ - Equipment tracking

---

## 📂 Example Package Structures

### **Bullying Report (Multi-dimensional)**
```
packages/student/safety/bullying-report-v1.0.0.hubpkg
```
- **Audience:** student
- **Purpose:** safety
- **Searchable by:** "student", "safety"
- **Use case:** Students report bullying incidents

**Alternative locations (equally valid):**
```
packages/safety/student/bullying-report-v1.0.0.hubpkg  (safety-first organization)
packages/schoolwide/safety/bullying-report-v1.0.0.hubpkg  (if available to all)
```

### **Maintenance Work Order**
```
packages/staff/maintenance/work-order-v1.0.0.hubpkg
```
- **Tags:** `staff`, `maintenance`
- **Use case:** Staff submits maintenance requests

### **Parent Newsletter**
```
packages/parent/communication/newsletter-v1.0.0.hubpkg
```
- **Tags:** `parent`, `communication`
- **Use case:** Parents receive school newsletters

### **Cafeteria Inventory**
```
packages/staff/nutrition/inventory-v1.0.0.hubpkg
```
- **Tags:** `staff`, `nutrition`
- **Use case:** Track cafeteria food inventory

### **Athletic Physical Form**
```
packages/student/athletics/physical-form-v1.0.0.hubpkg
```
- **Tags:** `student`, `athletics`
- **Use case:** Students submit physical exam forms for sports

### **Facility Inspection**
```
packages/staff/maintenance/facilities/inspection-v1.0.0.hubpkg
```
- **Tags:** `staff`, `maintenance`, `facilities`
- **Use case:** Regular building inspections

### **School-Wide Survey**
```
packages/schoolwide/forms/surveys/climate-survey-v1.0.0.hubpkg
```
- **Tags:** `schoolwide`, `forms`, `surveys`
- **Use case:** Annual school climate survey for all stakeholders

---

## 🎨 Tag Icons & Colors

The system automatically assigns icons and colors:

| Tag | Icon | Color | Use For |
|-----|------|-------|---------|
| student | 🎓 | primary (blue) | Student-facing |
| staff | 👥 | info (cyan) | Staff tools |
| parent | 👨‍👩‍👧 | success (green) | Parent/guardian |
| maintenance | 🔧 | warning (yellow) | Facilities |
| safety | 🛡️ | danger (red) | Security/safety |
| reporting | 📋 | secondary (gray) | Reports/analytics |
| schoolwide | 🏫 | dark | Entire community |
| communication | 💬 | info | Messaging |
| facilities | 🏢 | warning | Buildings |
| forms | 📝 | success | Data entry |
| workflows | ⚙️ | secondary | Approvals |
| analytics | 📊 | primary | Dashboards |

*Add more in `getTagIcon()` and `getTagColor()` functions in admin.js*

---

## 🔍 UI Filtering

### **How Users Filter:**

1. **Search Bar:** Type package name or description
2. **Tag Buttons:** Click tags to filter (multi-select)
   - Click again to deselect
   - Packages must match **at least one** selected tag
3. **Clear All:** Remove all tag filters

### **Example User Journey:**
```
User wants student safety tools:
1. Click "student" tag (🎓 student)
2. Click "safety" tag (🛡️ safety)
3. See only packages tagged with student OR safety
4. Use search to narrow further
```

---

## 💡 Best Practices

### **1. Use 2-3 Tags Maximum**
```
✅ Good: packages/student/safety/
✅ Good: packages/staff/maintenance/facilities/
❌ Avoid: packages/a/b/c/d/e/f/package.hubpkg (too deep)
```

### **2. Order by Most Important**
```
✅ Primary focus first: packages/safety/student/
✅ Department first: packages/maintenance/facilities/
```

### **3. Be Consistent**
```
✅ Use: student, staff, parent
❌ Avoid: students, staffmembers, parents
```

### **4. Use Singular Form**
```
✅ maintenance, facility, form
❌ maintenances, facilities, forms
```

### **5. Lowercase Only**
```
✅ student, safety
❌ Student, SAFETY, Safety
```

---

## 📝 Naming Conventions

### **Package Filename:**
```
{name}-v{version}.hubpkg

Examples:
✅ bullying-report-v1.0.0.hubpkg
✅ work-order-v2.1.3.hubpkg
✅ parent-newsletter-v1.0.0.hubpkg
```

### **Directory Naming:**
```
{audience}/{purpose}/package.hubpkg

Examples:
packages/student/safety/bullying-report-v1.0.0.hubpkg
packages/staff/maintenance/work-order-v1.0.0.hubpkg
```

---

## 🚀 Migration Guide

### **Moving Existing Packages:**

**Old Structure:**
```
packages/
├── reporting/bullying-report.hubpkg
├── forms/contact-form.hubpkg
└── workflows/approval.hubpkg
```

**New Structure (Tagging-Ready):**
```
packages/
├── student/safety/bullying-report.hubpkg
├── schoolwide/forms/contact-form.hubpkg
└── staff/workflows/approval.hubpkg
```

### **Steps:**
1. Identify package audience (student/staff/parent/schoolwide)
2. Identify package purpose (safety/forms/workflows/etc.)
3. Create new directory structure
4. Move package files
5. Commit changes
6. Test discovery in UI

---

## 🔧 Technical Details

### **API Response:**
```json
{
  "id": "bullying-report",
  "name": "bullying-report",
  "version": "1.0.0",
  "path": "packages/student/safety/bullying-report-v1.0.0.hubpkg",
  "category": "student",
  "tags": ["student", "safety"],
  "download_url": "https://..."
}
```

### **Frontend Rendering:**
```html
<div class="package-card-wrapper" data-tags="student,safety">
  <div class="card">
    <span class="badge bg-primary">🎓 student</span>
    <span class="badge bg-danger">🛡️ safety</span>
  </div>
</div>
```

### **Filter Logic:**
```javascript
// Package matches if it has ANY of the selected tags
const matchesTags = activeFilters.size === 0 || 
    [...activeFilters].some(filter => packageTags.includes(filter));
```

---

## 📋 Quick Reference

### **Common Package Paths:**

| Package Type | Path | Tags |
|-------------|------|------|
| Bullying Report | `student/safety/` | student, safety |
| Work Order | `staff/maintenance/` | staff, maintenance |
| Parent Form | `parent/forms/` | parent, forms |
| Bus Incident | `transportation/safety/` | transportation, safety |
| Lunch Order | `student/nutrition/` | student, nutrition |
| Athletic Physical | `student/athletics/` | student, athletics |
| Facility Inspection | `staff/maintenance/facilities/` | staff, maintenance, facilities |
| Climate Survey | `schoolwide/surveys/` | schoolwide, surveys |
| IT Ticket | `staff/it/` | staff, it |
| Library Checkout | `student/library/` | student, library |

---

## 🎯 Future Enhancements

### **Possible Additions:**
- **Required Tags:** Enforce audience tag + purpose tag
- **Tag Aliases:** Map variations to standard tags
- **Tag Descriptions:** Hover tooltips explaining each tag
- **Tag Hierarchy:** Parent/child tag relationships
- **Tag Suggestions:** Auto-suggest tags based on package content
- **Custom Tags:** Allow repos to define custom tag sets

---

**Last Updated:** November 10, 2025  
**Version:** 1.0.0
