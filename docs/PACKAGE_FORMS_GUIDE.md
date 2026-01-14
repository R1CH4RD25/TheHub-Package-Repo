# Package Forms System Guide

**Dynamic Form Builder for The Hub Packages**

This guide explains how to create, edit, and manage forms for your packages using The Hub's visual form builder.

---

## 🎨 Visual Form Builder

### Access
Navigate to: **Admin Dashboard → Form Builder** (`/admin/form-builder.php`)

### Quick Start

1. **Select Package** - Choose the package you're creating a form for
2. **Load or Create**
   - **New Form**: Leave "Load Existing Form" as "Create New"
   - **Edit Form**: Select existing form from dropdown
3. **Name Your Form** - e.g., "Maintenance Request", "Room Reservation"
4. **Build Form**
   - Drag fields from left palette to center canvas
   - Click fields to edit properties (label, placeholder, required, etc.)
   - Reorder fields by dragging in canvas
   - Delete fields with trash icon
5. **Save** - Stores form definition to database

---

## 📋 Field Types

### Basic Fields
- **Text Input** - Single-line text (`text`)
- **Text Area** - Multi-line text (`textarea`)
- **Number** - Numeric input (`number`)
- **Email** - Email address with validation (`email`)
- **Phone** - Phone number (`tel`)

### Choice Fields
- **Dropdown** - Select one from list (`dropdown`)
- **Radio Buttons** - Choose one visually (`radio`)
- **Checkboxes** - Select multiple (`checkbox`)

### Date & Time
- **Date** - Calendar picker (`date`)
- **Date & Time** - Combined picker (`datetime`)
- **Time** - Time selector (`time`)

### Advanced
- **File Upload** - Document/image attachments (`file`)
- **User Selector** - Choose from system users (`user_select`)

---

## 🛠️ Field Configuration

Each field can be customized with:

### Basic Properties
- **Field Label** - Display text above field (required)
- **Field Key** - Internal identifier for database storage (auto-generated, customizable)
- **Placeholder** - Gray hint text inside field
- **Help Text** - Additional instructions below label
- **Required** - Mark as mandatory field

### Choice Field Options
For dropdown, radio, and checkbox fields:
- Click "Add Option" to create choices
- Each option has:
  - **Label** - Text user sees
  - **Value** - Internal value stored (auto-generated from label)
- Remove options with X button
- Minimum 1 option required

---

## 💾 Database Schema

Forms are stored in four tables:

### `package_forms`
Stores form definitions
```sql
- id: Form ID
- package_id: Parent package (sections.id)
- name: Form display name
- description: Help text
- context: hub|management|admin
- icon: Bootstrap icon class
- is_active: Enabled/disabled
```

### `package_form_fields`
Individual field configurations
```sql
- id: Field ID
- form_id: Parent form
- field_key: Internal identifier
- label: Display text
- field_type: text|dropdown|date|etc
- placeholder: Hint text
- help_text: Instructions
- options_json: For choice fields [{value, label}]
- validation_rules: {required, min, max, pattern}
- is_required: Mandatory flag
- display_order: Sort position
```

### `package_form_submissions`
User-submitted data
```sql
- id: Submission ID
- form_id: Which form
- submitted_by: User ID
- submitted_at: Timestamp
- data_json: Field values {field_key: value}
- status: pending|in_progress|completed|cancelled
- assigned_to: Workflow assignment
```

### `package_form_alerts`
Conditional notifications
```sql
- id: Alert ID
- form_id: Parent form
- alert_name: Identifier
- trigger_type: always|conditional
- recipient_type: user|role|org_role|email
- notification_method: email|sms|both
- email_subject: Template
- email_template: Body with {field_key} placeholders
```

---

## 📦 Package Form Inclusion

### Pre-Install Forms in Package Manifest

Include form definitions in `manifest.json`:

```json
{
  "forms": [
    {
      "name": "Maintenance Request",
      "context": "hub",
      "description": "Submit facility maintenance needs",
      "icon": "bi-tools",
      "fields": [
        {
          "field_key": "priority",
          "label": "Priority Level",
          "field_type": "dropdown",
          "is_required": true,
          "options": [
            {"value": "low", "label": "Low - Can wait"},
            {"value": "medium", "label": "Medium - This week"},
            {"value": "high", "label": "High - Urgent"}
          ]
        },
        {
          "field_key": "description",
          "label": "Issue Description",
          "field_type": "textarea",
          "placeholder": "Describe the problem in detail...",
          "is_required": true
        },
        {
          "field_key": "location",
          "label": "Building/Room",
          "field_type": "text",
          "placeholder": "e.g., Main Office Room 101",
          "is_required": true
        },
        {
          "field_key": "preferred_date",
          "label": "Preferred Service Date",
          "field_type": "date",
          "is_required": false
        },
        {
          "field_key": "photos",
          "label": "Photos/Documents",
          "field_type": "file",
          "help_text": "Optional: Attach photos of the issue",
          "is_required": false
        }
      ],
      "alerts": [
        {
          "alert_name": "High Priority Alert",
          "trigger_type": "conditional",
          "conditions": [
            {"field": "priority", "operator": "equals", "value": "high"}
          ],
          "recipient_type": "role",
          "recipient_id": 1,
          "notification_method": "email",
          "email_subject": "URGENT: Maintenance Request - {location}",
          "email_template": "A high-priority maintenance issue has been reported at {location}.\n\nDescription: {description}\n\nSubmitted by: {user_name}"
        }
      ]
    }
  ]
}
```

### Installation Process

When package is installed:
1. Forms array parsed from manifest
2. Entries created in `package_forms`
3. Fields inserted into `package_form_fields`
4. Alert rules added to `package_form_alerts`
5. Forms immediately available in package context

### Editing Pre-Installed Forms

Users can customize pre-installed forms:
1. Admin Dashboard → Form Builder
2. Select package
3. Load existing form from dropdown
4. Modify fields, add/remove, reorder
5. Save updates

---

## 🔔 Alert System (Future Enhancement)

### Conditional Notifications
- **Trigger**: When submission matches conditions
- **Recipients**: Users, roles, org roles, or email addresses
- **Methods**: Email, SMS, or both
- **Templates**: Use `{field_key}` placeholders

### Example Alert Rule
```json
{
  "alert_name": "Emergency Notification",
  "trigger_type": "conditional",
  "conditions": [
    {"field": "emergency", "operator": "equals", "value": "yes"}
  ],
  "recipient_type": "role",
  "recipient_id": 2,
  "notification_method": "both",
  "email_subject": "EMERGENCY: {issue_type}",
  "email_template": "Emergency reported: {description}\nLocation: {location}",
  "sms_template": "EMERGENCY at {location}: {description}"
}
```

---

## ✅ Best Practices

### Field Design
- ✅ Use **clear, concise labels** - "Priority Level" not "Priority"
- ✅ Add **help text** for complex fields
- ✅ Set **placeholders** showing format examples
- ✅ Mark **required fields** appropriately
- ✅ Use **dropdowns** for 3-10 options, radio for 2-4
- ✅ Use **checkboxes** for multi-select or yes/no options

### Field Keys
- ✅ Keep **lowercase** and **underscore_separated**
- ✅ Make **descriptive**: `building_id` not `bid`
- ✅ **Avoid renaming** after data collected (breaks reports)

### Form Organization
- ✅ Put **required fields first**
- ✅ Group **related fields** together
- ✅ Keep forms **under 15 fields** (use multiple forms if needed)
- ✅ Test form **on mobile devices**

### Validation
- ✅ Mark critical fields **required**
- ✅ Use **email/tel types** for automatic validation
- ✅ Add **min/max** for number fields
- ✅ Provide **helpful error messages**

---

## 🚀 Coming Soon

- **Conditional Field Logic** - Show/hide fields based on other selections
- **Multi-step Forms** - Wizard-style forms with progress indicator
- **Form Templates** - Pre-built common forms (contact, feedback, etc.)
- **Analytics Dashboard** - Submission trends and reports
- **PDF Export** - Generate PDFs from submissions
- **Webhooks** - Trigger external systems on submission

---

## 🐛 Troubleshooting

### Form Not Saving
- Check package is selected
- Ensure form name is filled
- At least one field required
- Check browser console for errors

### Fields Not Loading
- Verify form ID exists in database
- Check `package_form_fields` table
- Clear browser cache
- Check PHP error logs

### Submissions Not Recording
- Verify `package_form_submissions` table exists
- Check user permissions
- Validate JSON structure in `data_json`
- Review audit logs for errors

---

## 📚 Related Documentation

- [PACKAGE_CREATION_GUIDE.md](./PACKAGE_CREATION_GUIDE.md) - Package development
- [PACKAGE_SPECIFICATION_V2.md](./PACKAGE_SPECIFICATION_V2.md) - Technical specs
- [MODULE_CATALOG_V2.md](./MODULE_CATALOG_V2.md) - Module types reference

---

**Last Updated:** January 14, 2026  
**Version:** 1.0.0
