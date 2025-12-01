# 📋 Section Configuration - User Guide

## How to Configure the Bullying Report Section

### Step 1: Navigate to Section Configuration
1. Go to **Admin Dashboard** (`/admin/`)
2. In the left sidebar, expand **⚙️ Configuration** (click to expand)
3. Click **Section Configuration**

### Step 2: Find Your Section
You'll see a list of **collapsible section cards**. Each card shows:
- **Section icon** (e.g., 🛡️ for Bullying Report)
- **Section name**
- **Category badge** (shows current category: 📋 Reporting, 📊 Analytics, etc.)
- **Status badge** (✅ Configured, ⚠️ Not Configured, ❌ Error)

Example:
```
┌─────────────────────────────────────────────────────────────┐
│ 🛡️  Bullying Report                    📋 Reporting  ✅ Conf │
│                                                        ▼      │
└─────────────────────────────────────────────────────────────┘
```

### Step 3: Click to Expand
Click on the **Bullying Report** card to expand it. The configuration panel will load below the header.

### Step 4: Configure Each Section

The form is organized into **panels** based on the category you select:

#### 🏷️ **Category Selection** (Always visible)
- Select from dropdown:
  - 📋 Reporting & Forms (shows ALL options)
  - 📊 Analytics & Dashboards
  - 🔧 Tools & Utilities
  - 📚 Resources & Documents
  - ⚙️ Administration

> **Note:** For Bullying Report, choose **"📋 Reporting & Forms"**

---

#### 📝 **Submission Permissions** (Shows for: Reporting, Tools)
Who can submit to this section?

**Each row contains:**
- **Role dropdown** (student, staff, parent, teacher, counselor, principal, admin, super_admin)
- **Can Submit** checkbox
- **Allow Anonymous** checkbox
- **Delete button** (🗑️)

**Example Configuration:**
```
┌─ Submission Permissions ─────────────────────────────────────┐
│ Role: Student          ☑ Can Submit  ☑ Allow Anonymous       │
│ Role: Staff            ☑ Can Submit  ☐ Allow Anonymous       │
│ Role: Parent           ☑ Can Submit  ☑ Allow Anonymous       │
│ Role: Teacher          ☑ Can Submit  ☐ Allow Anonymous       │
│ [+ Add Permission Row]                                        │
└───────────────────────────────────────────────────────────────┘
```

**Click "+ Add Permission Row"** to add more roles.

---

#### 👁️ **Review Permissions** (Shows for: Reporting)
Who can view and manage submissions?

**Each row contains:**
- **Role dropdown**
- **7 permission checkboxes:**
  - ☑ Can View
  - ☑ Can Edit
  - ☑ Can Delete
  - ☑ Can Add Notes
  - ☑ Can Change Status
  - ☑ Can Assign
  - ☑ Can Export

**Example Configuration:**
```
┌─ Review Permissions ──────────────────────────────────────────┐
│ Role: Counselor                                                │
│   ☑ View  ☑ Edit  ☐ Delete  ☑ Add Notes  ☑ Change Status     │
│   ☑ Assign  ☑ Export                                          │
│                                                                │
│ Role: Principal                                                │
│   ☑ View  ☑ Edit  ☑ Delete  ☑ Add Notes  ☑ Change Status     │
│   ☑ Assign  ☑ Export                                          │
│                                                                │
│ [+ Add Permission Row]                                        │
└────────────────────────────────────────────────────────────────┘
```

---

#### 🔔 **Notification Rules** (Shows for: Reporting)
Who receives notifications and when?

**Each row contains:**
- **Role dropdown**
- **Event checkboxes:**
  - ☑ On Submission
  - ☑ Status Change
  - ☑ Assignment
  - ☑ Comment
- **Notification method checkboxes:**
  - ☑ Email
  - ☑ SMS

**Example Configuration:**
```
┌─ Notification Rules ──────────────────────────────────────────┐
│ Role: Counselor                                                │
│   Events: ☑ Submission  ☑ Status Change  ☑ Assignment  ☑ Cmt  │
│   Methods: ☑ Email  ☐ SMS                                      │
│                                                                │
│ Role: Principal                                                │
│   Events: ☑ Submission  ☑ Status Change  ☐ Assignment  ☐ Cmt  │
│   Methods: ☑ Email  ☐ SMS                                      │
│                                                                │
│ [+ Add Notification Rule]                                     │
└────────────────────────────────────────────────────────────────┘
```

---

#### 📖 **Guidelines** (Shows for: All except Administration)
Instructions shown to users

**Each guideline has:**
- **Type dropdown:**
  - Submission (shown on submission form)
  - Review (shown on dashboard)
  - General (shown everywhere)
- **Title** (text input)
- **Content** (textarea)

**Example Configuration:**
```
┌─ Guidelines ───────────────────────────────────────────────────┐
│ Type: Submission                                               │
│ Title: What to Include in Your Report                          │
│ Content: [Large text area]                                     │
│ Please provide as much detail as possible:                     │
│ • Date and time of the incident                                │
│ • Location where it occurred                                   │
│ • Names of people involved (if known)                          │
│                                                                │
│ [+ Add Guideline]                                             │
└────────────────────────────────────────────────────────────────┘
```

---

#### ⚙️ **Additional Options** (Shows for: Reporting)
Feature toggles for the section

```
┌─ Additional Options ──────────────────────────────────────────┐
│ ☑ Enable Status Tracking                                      │
│ ☑ Enable Priority Levels                                      │
│ ☑ Enable File Attachments                                     │
│ ☑ Enable Notes/Comments                                       │
│ ☑ Enable Assignment                                           │
└────────────────────────────────────────────────────────────────┘
```

---

### Step 5: Save Configuration
Click the **💾 Save Configuration** button at the bottom of the form.

---

## Quick Answer to Your Question

**Q: "How can I configure the Bullying Report Section? Will I select what section I want from say a dropdown and the configuration fill below?"**

**A:** Almost! Here's how it works:

1. **No dropdown needed** - All sections are shown as **collapsible cards**
2. Each card shows the section name (e.g., "Bullying Report")
3. **Click the card** to expand it
4. The configuration form loads **inside that card**
5. Fill out the form and click **Save**

**Think of it like an accordion:**
- Click "Bullying Report" → Configuration expands below
- Click "Travel Mileage" → Its configuration expands below
- Only one section expanded at a time (or multiple if you want)

---

## Visual Flow

```
Admin Dashboard
  └─ ⚙️ Configuration (sidebar - click to expand)
      └─ Section Configuration (click to open tab)
          └─ 📋 Sections List (collapsible cards)
              ├─ 🛡️ Bullying Report [CLICK HERE]
              │   └─ [Configuration Form Expands] ← You're here now!
              │       ├─ Category: Reporting ▼
              │       ├─ Submission Permissions
              │       ├─ Review Permissions
              │       ├─ Notification Rules
              │       ├─ Guidelines
              │       ├─ Additional Options
              │       └─ [💾 Save Configuration]
              │
              ├─ 📊 Travel Mileage [collapsed]
              └─ 🔧 Maintenance Requests [collapsed]
```

---

## Current Status

The **Bullying Report** section is **already pre-configured** with:
- ✅ Category: Reporting & Forms
- ✅ 4 submission roles (student, staff, parent, teacher)
- ✅ 4 review roles (counselor, principal, admin, super_admin)
- ✅ 3 notification recipients
- ✅ 9 guidelines (submission, review, general)
- ✅ All features enabled

**You can click it to see the configuration and modify it!**

---

## Tips

1. **Start with Category** - This determines what other sections show
2. **Add roles incrementally** - Use "+ Add" buttons
3. **Test as you go** - Save and check the actual section (e.g., `/modules/bullying-report/`)
4. **Guidelines are powerful** - Users see these instructions in real-time
5. **Permissions are granular** - You can give someone "view only" or "view + edit" etc.

---

## Troubleshooting

**Q: I don't see any sections**
- A: Click **Refresh** button (top right)

**Q: Section shows "⚠️ Not Configured"**
- A: Click to expand and set at least the Category

**Q: Changes aren't saving**
- A: Check browser console for errors (F12)
- Make sure you're logged in as admin/super_admin

**Q: I want to add a new section to configure**
- A: New sections are created in the **Management → Sections** tab
- Once created, they appear in **Section Configuration**

---

**Need more help?** Check the logs or ask me specific questions!
