# Workflow Module Examples

## Overview

The WorkflowRenderer provides a generic state machine for any approval process. It supports:

- **Multiple states** with custom labels, colors, and icons
- **Role-based transitions** with permission checks
- **Conditional transitions** based on record data
- **Optional/required comments** on state changes
- **Email notifications** on transitions
- **Audit trail** with complete history
- **Multi-tenant isolation** built-in

## Use Cases

1. **Employee Evaluations**: draft → submitted → approved/rejected
2. **Purchase Requests**: requested → manager review → finance approval → ordered
3. **Time-Off Requests**: submitted → manager review → HR approval → scheduled
4. **Document Approvals**: draft → peer review → manager approval → published
5. **Incident Reports**: reported → investigating → resolved → closed
6. **Onboarding Tasks**: pending → in progress → awaiting verification → completed

---

## Example 1: Simple Approval (2 States, 3 Transitions)

```json
{
    "id": "approval-workflow",
    "type": "Workflow",
    "title": "Approval Workflow",
    "table": "requests",
    "stateField": "status",
    "ownerField": "created_by",
    
    "states": [
        {
            "name": "pending",
            "label": "Pending Review",
            "color": "warning",
            "icon": "bi-clock"
        },
        {
            "name": "approved",
            "label": "Approved",
            "color": "success",
            "icon": "bi-check-circle"
        },
        {
            "name": "rejected",
            "label": "Rejected",
            "color": "danger",
            "icon": "bi-x-circle"
        }
    ],
    
    "transitions": [
        {
            "from": "pending",
            "to": "approved",
            "label": "Approve",
            "color": "success",
            "icon": "bi-check-lg",
            "requiredRole": "manager",
            "allowComment": true,
            "commentLabel": "Approval Notes (optional)",
            "notify": ["owner"]
        },
        {
            "from": "pending",
            "to": "rejected",
            "label": "Reject",
            "color": "danger",
            "icon": "bi-x-lg",
            "requiredRole": "manager",
            "requireComment": true,
            "commentLabel": "Reason for Rejection",
            "commentPlaceholder": "Please explain why this request is being rejected...",
            "notify": ["owner"]
        },
        {
            "from": "rejected",
            "to": "pending",
            "label": "Resubmit",
            "color": "primary",
            "icon": "bi-arrow-repeat",
            "requireOwner": true,
            "requireComment": true,
            "commentLabel": "What has changed?",
            "notify": ["manager"]
        }
    ],
    
    "displayFields": [
        {
            "field": "title",
            "label": "Request Title"
        },
        {
            "field": "description",
            "label": "Description"
        },
        {
            "field": "created_by",
            "label": "Submitted By",
            "format": "user"
        },
        {
            "field": "created_at",
            "label": "Submitted On",
            "format": "datetime"
        }
    ]
}
```

**Database Schema**:
```sql
CREATE TABLE requests (
    id CHAR(26) PRIMARY KEY,
    tenant_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(50) DEFAULT 'pending',
    created_by CHAR(26),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_status (tenant_id, status)
);
```

---

## Example 2: Multi-Step Approval (4 States)

```json
{
    "id": "purchase-workflow",
    "type": "Workflow",
    "title": "Purchase Request Workflow",
    "table": "purchase_requests",
    "stateField": "status",
    
    "states": [
        {
            "name": "submitted",
            "label": "Submitted",
            "color": "info",
            "icon": "bi-send"
        },
        {
            "name": "manager_approved",
            "label": "Manager Approved",
            "color": "primary",
            "icon": "bi-check"
        },
        {
            "name": "finance_approved",
            "label": "Finance Approved",
            "color": "success",
            "icon": "bi-check-all"
        },
        {
            "name": "rejected",
            "label": "Rejected",
            "color": "danger",
            "icon": "bi-x-circle"
        }
    ],
    
    "transitions": [
        {
            "from": "submitted",
            "to": "manager_approved",
            "label": "Approve (Manager)",
            "color": "primary",
            "requiredRole": "manager",
            "allowComment": true,
            "notify": ["finance", "owner"]
        },
        {
            "from": "submitted",
            "to": "rejected",
            "label": "Reject",
            "color": "danger",
            "requiredRole": ["manager", "finance"],
            "requireComment": true,
            "notify": ["owner"]
        },
        {
            "from": "manager_approved",
            "to": "finance_approved",
            "label": "Approve (Finance)",
            "color": "success",
            "requiredRole": "finance",
            "conditions": [
                {
                    "field": "amount",
                    "operator": "<=",
                    "value": 10000
                }
            ],
            "allowComment": true,
            "notify": ["owner", "purchasing"],
            "onTransition": {
                "type": "updateField",
                "field": "approved_date",
                "value": "NOW()"
            }
        },
        {
            "from": "manager_approved",
            "to": "rejected",
            "label": "Reject (Finance)",
            "color": "danger",
            "requiredRole": "finance",
            "requireComment": true,
            "notify": ["owner", "manager"]
        }
    ],
    
    "displayFields": [
        {
            "field": "item_description",
            "label": "Item Description"
        },
        {
            "field": "amount",
            "label": "Amount",
            "format": "currency"
        },
        {
            "field": "vendor",
            "label": "Vendor"
        },
        {
            "field": "created_by",
            "label": "Requested By",
            "format": "user"
        },
        {
            "field": "created_at",
            "label": "Request Date",
            "format": "date"
        }
    ]
}
```

---

## Example 3: Employee Evaluation Workflow

```json
{
    "id": "evaluation-workflow",
    "type": "Workflow",
    "title": "Evaluation Approval",
    "table": "employee_evaluations",
    "stateField": "status",
    
    "states": [
        {
            "name": "draft",
            "label": "Draft",
            "color": "secondary",
            "icon": "bi-pencil"
        },
        {
            "name": "submitted",
            "label": "Submitted",
            "color": "info",
            "icon": "bi-send"
        },
        {
            "name": "under_review",
            "label": "Under Review",
            "color": "warning",
            "icon": "bi-eye"
        },
        {
            "name": "approved",
            "label": "Approved",
            "color": "success",
            "icon": "bi-check-circle"
        },
        {
            "name": "needs_revision",
            "label": "Needs Revision",
            "color": "danger",
            "icon": "bi-arrow-counterclockwise"
        }
    ],
    
    "transitions": [
        {
            "from": "draft",
            "to": "submitted",
            "label": "Submit for Review",
            "color": "primary",
            "icon": "bi-send",
            "requireOwner": true,
            "notify": ["manager"],
            "onTransition": {
                "type": "updateField",
                "field": "submitted_at",
                "value": "NOW()"
            }
        },
        {
            "from": "submitted",
            "to": "under_review",
            "label": "Begin Review",
            "color": "warning",
            "requiredRole": "manager",
            "notify": ["owner", "hr"]
        },
        {
            "from": "under_review",
            "to": "approved",
            "label": "Approve Evaluation",
            "color": "success",
            "icon": "bi-check-lg",
            "requiredRole": ["manager", "hr"],
            "allowComment": true,
            "commentLabel": "Final Comments (optional)",
            "notify": ["owner", "hr", "manager"],
            "onTransition": {
                "type": "updateField",
                "field": "approved_at",
                "value": "NOW()"
            }
        },
        {
            "from": "under_review",
            "to": "needs_revision",
            "label": "Request Revision",
            "color": "danger",
            "icon": "bi-arrow-counterclockwise",
            "requiredRole": ["manager", "hr"],
            "requireComment": true,
            "commentLabel": "What needs to be revised?",
            "notify": ["owner"]
        },
        {
            "from": "needs_revision",
            "to": "submitted",
            "label": "Resubmit",
            "color": "primary",
            "requireOwner": true,
            "requireComment": true,
            "commentLabel": "What was changed?",
            "notify": ["manager"]
        }
    ],
    
    "displayFields": [
        {
            "field": "employee_name",
            "label": "Employee"
        },
        {
            "field": "evaluation_period",
            "label": "Evaluation Period"
        },
        {
            "field": "overall_rating",
            "label": "Overall Rating"
        },
        {
            "field": "created_by",
            "label": "Evaluator",
            "format": "user"
        },
        {
            "field": "submitted_at",
            "label": "Submitted",
            "format": "datetime"
        },
        {
            "field": "approved_at",
            "label": "Approved",
            "format": "datetime"
        }
    ]
}
```

---

## Configuration Reference

### States

```json
{
    "name": "state_name",           // Required: Internal state name
    "label": "Display Label",       // Required: Human-readable label
    "color": "primary",             // Bootstrap color: primary, secondary, success, danger, warning, info
    "icon": "bi-icon-name"          // Bootstrap icon class
}
```

### Transitions

```json
{
    "from": "current_state",        // Required: Starting state
    "to": "next_state",             // Required: Destination state
    "label": "Button Text",         // Required: Button label
    "color": "primary",             // Button color
    "icon": "bi-icon",              // Button icon
    
    // Permissions
    "requiredRole": "manager",      // Single role or array: ["manager", "hr"]
    "requireOwner": true,           // Only record owner can perform
    
    // Comments
    "allowComment": true,           // Allow optional comment
    "requireComment": true,         // Require comment
    "commentLabel": "Label",        // Comment field label
    "commentPlaceholder": "Text",   // Comment placeholder
    
    // Conditions (all must be true)
    "conditions": [
        {
            "field": "field_name",
            "operator": "=",        // =, !=, >, >=, <, <=, in, not_in, contains
            "value": "compare_value"
        }
    ],
    
    // Notifications
    "notify": ["owner", "manager"], // Who to notify: owner, role names, emails
    
    // Actions
    "onTransition": {
        "type": "updateField",      // updateField, webhook
        "field": "field_name",
        "value": "new_value"
    }
}
```

### Display Fields

```json
{
    "field": "database_column",     // Required: Column name
    "label": "Display Label",       // Required: Label text
    "format": "text"                // text, date, datetime, currency, email, user
}
```

---

## Conditional Transitions

Restrict transitions based on record data:

```json
{
    "from": "submitted",
    "to": "auto_approved",
    "conditions": [
        {
            "field": "amount",
            "operator": "<=",
            "value": 500
        },
        {
            "field": "category",
            "operator": "in",
            "value": ["supplies", "software"]
        }
    ]
}
```

**Supported Operators**:
- `=` or `==` - Equals
- `!=` - Not equals
- `>` - Greater than
- `>=` - Greater than or equal
- `<` - Less than
- `<=` - Less than or equal
- `in` - Value in array
- `not_in` - Value not in array
- `contains` - String contains substring

---

## Notification Recipients

```json
"notify": [
    "owner",                    // Record creator
    "manager",                  // Users with 'manager' role
    "hr",                       // Users with 'hr' role
    "admin@example.com",        // Specific email address
    {"user": "user_ulid"},      // Specific user by ID
    {"field": "approver_id"}    // User ID from field
]
```

---

## Transition Actions

Execute actions when transition occurs:

### Update Field
```json
"onTransition": {
    "type": "updateField",
    "field": "approved_date",
    "value": "NOW()"
}
```

### Webhook (Future)
```json
"onTransition": {
    "type": "webhook",
    "url": "https://api.example.com/notify",
    "method": "POST",
    "payload": {
        "record_id": "{id}",
        "new_state": "{status}"
    }
}
```

---

## Integration with Other Modules

### Form + Workflow

1. **Form module** creates record in "draft" state
2. **Workflow module** manages approval process
3. **TableView module** shows all records with status badges

```json
{
    "modules": [
        {
            "id": "create-request",
            "type": "Form",
            "onSubmit": {
                "insertInto": "requests",
                "defaults": {
                    "status": "draft"
                },
                "redirect": "/package-view.php?package=requests&module=workflow&record_id={id}"
            }
        },
        {
            "id": "workflow",
            "type": "Workflow",
            "table": "requests"
        },
        {
            "id": "view-all",
            "type": "TableView",
            "dataSource": {
                "table": "requests"
            },
            "rowActions": [
                {
                    "label": "View Workflow",
                    "url": "/package-view.php?package=requests&module=workflow&record_id={id}"
                }
            ]
        }
    ]
}
```

---

## Best Practices

1. **Start Simple**: Begin with 2-3 states and basic transitions
2. **Clear Labels**: Use action verbs ("Approve", "Reject", "Submit")
3. **Require Comments**: Always require comments on rejections
4. **Notify Stakeholders**: Alert relevant users on status changes
5. **Audit Everything**: History is automatically tracked
6. **Test Permissions**: Verify role checks work correctly
7. **Use Conditions**: Automate simple approvals with conditions
8. **Plan States**: Map out state diagram before implementation

---

## URL Pattern

Access workflow for specific record:

```
/package-view.php?package={package-slug}&module={workflow-module-id}&record_id={record-ulid}
```

Example:
```
/package-view.php?package=employee-eval&module=approval-workflow&record_id=01HQZX123456789ABCDEFGHIJK
```

---

## Security Features

- ✅ **Role-based permissions** - `requiredRole` checks
- ✅ **Owner restrictions** - `requireOwner` flag
- ✅ **Multi-tenant isolation** - Automatic `tenant_id` filtering
- ✅ **CSRF protection** - Token validation on all transitions
- ✅ **Audit trail** - All transitions logged with user/timestamp
- ✅ **Conditional logic** - Prevent invalid state changes
- ✅ **Comment requirements** - Force documentation on critical transitions

---

## Future Enhancements

- [ ] Email integration (when EmailNotificationRenderer complete)
- [ ] Webhook triggers for external systems
- [ ] Parallel approval paths (multiple approvers required)
- [ ] Timed transitions (auto-escalate after X days)
- [ ] Bulk transitions (approve multiple records)
- [ ] Custom approval chains (dynamic routing)
- [ ] Visual workflow diagram generator
