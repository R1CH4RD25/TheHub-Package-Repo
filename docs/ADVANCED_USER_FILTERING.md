# Advanced User Management & Filtering - Implementation Plan

## Overview

As the platform scales to handle students in addition to staff, the user management system needs powerful **filtering, searching, and pagination** capabilities.

## Current Limitation

- Basic user table with minimal filtering
- No pagination (problematic with 100+ users)
- No search functionality
- No role/group filtering
- No bulk actions

## Future Requirements

### 1. User Groups/Categories

Users will belong to different groups:
- **Staff** (teachers, administrators, support staff)
- **Students** (potentially hundreds)
- **Maintenance** (current system focus)
- **Custodial** (facilities staff)
- **Cafeteria** (food service staff)

### 2. Advanced Filtering

**Filter by:**
- ✅ Role (super_admin, admin, principal, counselor, etc.)
- ✅ Group (Staff, Student, Maintenance, Custodial, Cafeteria)
- ✅ Status (active, inactive, pending approval)
- ✅ Global roles assigned (filter users with specific global roles)
- ✅ Section access (users who can access specific sections)
- ✅ Last login date (active users vs inactive)
- ✅ Registration date (new users vs established)

**Search by:**
- ✅ Name (first, last, or full name)
- ✅ Email address
- ✅ User ID
- ✅ Partial matches (fuzzy search)

### 3. Pagination

- Display 25/50/100/All users per page
- Page navigation (1, 2, 3... Next, Previous)
- Total count indicator ("Showing 1-25 of 347 users")
- Jump to page functionality

### 4. Sorting

- Sort by: Name, Email, Role, Last Login, Registration Date
- Ascending/Descending toggle
- Persist sort preference in session

### 5. Bulk Actions

- Select multiple users (checkboxes)
- Bulk role assignment
- Bulk section access grant/revoke
- Bulk activate/deactivate
- Export selected users

### 6. Performance Optimization

- Database indexes on searchable columns
- AJAX loading for instant filtering
- Debounced search input (wait 300ms after typing)
- Lazy loading for large datasets
- Caching frequently accessed data

## Database Schema Updates

### Add user_group column to users table

```sql
ALTER TABLE users 
ADD COLUMN user_group ENUM(
    'staff',
    'student', 
    'maintenance',
    'custodial',
    'cafeteria',
    'other'
) DEFAULT 'staff' COMMENT 'Organizational group' AFTER role;
```

### Add indexes for performance

```sql
-- Existing: INDEX on email (UNIQUE)
-- Add composite indexes for common queries

CREATE INDEX idx_users_role_group ON users(role, user_group);
CREATE INDEX idx_users_group_active ON users(user_group, is_active);
CREATE INDEX idx_users_last_login ON users(last_login_at);
CREATE INDEX idx_users_name_search ON users(first_name, last_name);
CREATE INDEX idx_users_email_search ON users(email);
```

### Create user_filters table for saved filters

```sql
CREATE TABLE IF NOT EXISTS user_filters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'User who created filter',
    filter_name VARCHAR(100) NOT NULL COMMENT 'Name of saved filter',
    filter_config JSON NOT NULL COMMENT 'Filter parameters',
    is_default BOOLEAN DEFAULT FALSE COMMENT 'Load this filter by default',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_filters (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Saved filter presets for user management';
```

## API Endpoints

### `/api/users.php?action=filter`

**Request Parameters:**
```json
{
    "page": 1,
    "per_page": 25,
    "search": "john",
    "filters": {
        "role": ["staff", "principal"],
        "user_group": ["staff"],
        "status": "active",
        "has_global_roles": true,
        "section_access": [1, 2, 3],
        "last_login_after": "2025-01-01",
        "registered_after": "2024-01-01"
    },
    "sort": {
        "column": "last_name",
        "direction": "asc"
    }
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "users": [...],
        "pagination": {
            "current_page": 1,
            "per_page": 25,
            "total_users": 347,
            "total_pages": 14,
            "has_next": true,
            "has_previous": false
        },
        "filters_applied": {
            "search": "john",
            "role": ["staff", "principal"],
            "user_group": ["staff"]
        }
    }
}
```

### `/api/users.php?action=bulk`

**Bulk Actions Endpoint:**
```json
{
    "action": "assign_role",
    "user_ids": [12, 34, 56, 78],
    "role": "maintenance_staff"
}
```

Supported bulk actions:
- `assign_role` - Change primary role
- `add_global_role` - Add global role
- `remove_global_role` - Remove global role
- `grant_section_access` - Grant section access
- `revoke_section_access` - Revoke section access
- `activate` - Activate users
- `deactivate` - Deactivate users

## Frontend UI Components

### Filter Panel (Collapsible)

```
┌─────────────────────────────────────────────────────────────┐
│ 🔍 Filter Users                                    [▼ Hide] │
├─────────────────────────────────────────────────────────────┤
│ Search: [________________] 🔍                                │
│                                                              │
│ Role:        [x] Staff  [x] Principal  [ ] Student          │
│ Group:       [x] Staff  [ ] Student    [ ] Maintenance      │
│ Status:      (•) All    ( ) Active     ( ) Inactive         │
│ Global Roles: [x] Has global roles assigned                 │
│                                                              │
│ [Clear Filters]  [Save Filter As...]  [Apply]              │
└─────────────────────────────────────────────────────────────┘
```

### User Table with Pagination

```
┌─────────────────────────────────────────────────────────────┐
│ Showing 1-25 of 347 users          [25 ▼] per page         │
├──┬────────────────┬─────────────────────┬──────────┬────────┤
│☑│ Name ▲        │ Email               │ Role     │ Group  │
├──┼────────────────┼─────────────────────┼──────────┼────────┤
│☑│ John Doe       │ john@woodsonisd.net │ Staff    │ Staff  │
│☑│ Jane Smith     │ jane@woodsonisd.net │ Principal│ Staff  │
│☑│ Bob Johnson    │ bob@woodsonisd.net  │ Student  │ Student│
└──┴────────────────┴─────────────────────┴──────────┴────────┘

[◄ Previous]  [1] [2] 3 [4] [5] ... [14]  [Next ►]

Selected: 3 users
[Bulk Actions ▼] [Assign Role] [Grant Access] [Export]
```

### Quick Stats Dashboard

```
┌───────────────────────────────────────────────────────────┐
│ 📊 User Statistics                                        │
├───────────────────────────────────────────────────────────┤
│  Total Users: 347    Active: 312    Pending: 15          │
│  Students: 245       Staff: 89       Other: 13           │
│  New This Month: 8   Last 7 Days: 3                      │
└───────────────────────────────────────────────────────────┘
```

## Dynamic Hub Display Based on Role

### Current Behavior
- All users see "Staff Hub" section

### New Behavior
```php
// In section display logic
if (Auth::hasRole('student')) {
    // Show "Student Hub" instead of "Staff Hub"
    $hub = getSectionBySlug('student-hub');
} else {
    // Show "Staff Hub"
    $hub = getSectionBySlug('staff-hub');
}
```

### Hub Section Visibility Rules

**Student Hub (`student-hub`):**
- Visible to: student role only
- Contains:
  - Counselor Request form
  - Bullying Report form
  - Academic calendar
  - Student resources

**Staff Hub (`staff-hub`):**
- Visible to: all non-student roles
- Contains:
  - Staff directory
  - Professional development
  - Internal resources
  - Department links

## Implementation Phases

### Phase 1: Database & Backend (Week 1)
- [x] Create migration 003 with section types ✅
- [ ] Add user_group column to users table
- [ ] Create indexes for search/filter performance
- [ ] Update User.php class with filtering methods
- [ ] Create UserFilter.php class for complex queries
- [ ] Update `/api/users.php` with filter endpoint

### Phase 2: Frontend Filtering (Week 2)
- [ ] Build filter panel UI component
- [ ] Implement search with debouncing
- [ ] Add role/group checkboxes
- [ ] Implement AJAX filtering
- [ ] Add loading states and spinners

### Phase 3: Pagination (Week 3)
- [ ] Build pagination component
- [ ] Implement page navigation
- [ ] Add per-page selector
- [ ] Update API to return pagination metadata
- [ ] Persist pagination preferences

### Phase 4: Bulk Actions (Week 4)
- [ ] Add checkbox column to user table
- [ ] Build bulk action dropdown
- [ ] Implement bulk API endpoint
- [ ] Add confirmation modals
- [ ] Show success/error feedback

### Phase 5: Advanced Features (Week 5+)
- [ ] Saved filter presets
- [ ] Export functionality (CSV/XLSX)
- [ ] Quick stats dashboard
- [ ] Column visibility toggles
- [ ] Mobile responsive improvements

## Example: Filtering Large Student Lists

**Scenario:** Principal wants to see all 10th grade students with counselor access

**Filter Config:**
```json
{
    "user_group": ["student"],
    "section_access": [/* counselor-request section id */],
    "custom_field": {
        "grade": "10"
    }
}
```

**SQL Generated:**
```sql
SELECT u.* 
FROM users u
LEFT JOIN user_global_roles ugr ON u.id = ugr.user_id
LEFT JOIN section_role_access sra ON u.role = sra.role
WHERE u.user_group = 'student'
  AND sra.section_id = 7
  AND u.custom_fields->>'$.grade' = '10'
ORDER BY u.last_name ASC
LIMIT 25 OFFSET 0;
```

## Performance Considerations

### Expected Load
- Students: ~300-500 users
- Staff: ~50-100 users
- Total: ~400-600 concurrent users in system

### Database Optimization
- Composite indexes on filtered columns
- Query result caching (Redis if needed)
- Pagination prevents loading all users

### Frontend Optimization
- Virtual scrolling for large tables (optional)
- Debounced search (300ms delay)
- Lazy load user avatars/photos
- Progressive rendering

## Security Considerations

### Role-Based Filtering
- Students can only see other students (limited)
- Staff can see all staff
- Admins can see everyone
- Super admins have unrestricted access

### Data Privacy
- Email addresses masked for students
- Phone numbers only visible to counselors/admin
- Student records require higher permissions
- Audit log for bulk actions

## Testing Scenarios

1. **Search Performance**
   - Search 500 users by name in <200ms
   - Partial matches work correctly
   - Special characters handled properly

2. **Filter Combinations**
   - Multiple roles + group + status
   - Empty result sets handled gracefully
   - Filter persistence across page reloads

3. **Pagination Edge Cases**
   - Last page with < per_page users
   - Jump to invalid page number
   - Change per_page value maintains position

4. **Bulk Actions**
   - Select all across pages
   - Deselect all
   - Action on 100+ users
   - Rollback on partial failures

## Future Enhancements

1. **Saved Filter Presets**
   - "New Students This Week"
   - "Inactive Staff (30+ days)"
   - "Students Needing Counselor"

2. **Export Options**
   - Export current view to CSV
   - Export with custom columns
   - Scheduled exports (email weekly)

3. **User Import**
   - Bulk CSV import for students
   - Sync with student information system (SIS)
   - Automatic role assignment rules

4. **Analytics Dashboard**
   - User growth charts
   - Role distribution pie chart
   - Login activity heatmap
   - Section usage statistics

## Next Steps

**Immediate Priority:**
1. Run migration 003 to add section types and notifications ✅
2. Test section type categorization
3. Create user_group column migration
4. Build basic filter UI prototype
5. Implement search functionality first (highest ROI)

**Medium Priority:**
6. Add pagination
7. Implement role/group filters
8. Build bulk actions

**Lower Priority:**
9. Saved filters
10. Advanced analytics
11. Import/export features
