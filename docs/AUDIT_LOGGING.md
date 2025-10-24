# Comprehensive Audit Logging System

## Overview

The Woodson ISD Maintenance system now features **complete audit logging** for all user actions. Every form submission, data change, user management action, and authentication event is logged with full details.

## What Gets Logged

### 🔐 Authentication Events
- **Login Success** - Every successful user login
- **Login Failed** - Failed login attempts
- **Logout** - When users sign out

### 👥 User Management
- **User Approval** - When admins approve pending users
- **Role Changes** - When user roles are modified (single role or multi-role)
- **User Activation** - When deactivated users are reactivated
- **User Deactivation** - When users are deactivated
- **User Deletion** - When users are permanently deleted
- **Multi-Role Grants** - When users are assigned multiple platform roles

### 🚗 Vehicle Management
- **Vehicle Creation** - New vehicles added to fleet
- **Vehicle Updates** - Changes to vehicle details
- **Vehicle Deactivation** - When vehicles are taken out of service

### ⛽ Fuel Records
- **Fuel Entry Creation** - Every fuel/mileage entry submitted
- **Fuel Entry Updates** - Edits to existing fuel records (admin only)
- **Fuel Entry Deletion** - When records are deleted (super admin only)

### 📦 Section Management (Future Travel/Reimbursement/etc.)
- **Section Creation** - New platform sections added
- **Section Updates** - Changes to section settings
- **Section Activation/Deactivation** - When sections are enabled/disabled
- **Section Deletion** - When sections are removed

### 🔑 Access Control
- **Grant Access** - When users are given access to sections
- **Revoke Access** - When user access to sections is removed

## Audit Log Data Captured

Each log entry contains:

| Field | Description |
|-------|-------------|
| **User** | Who performed the action (name and email) |
| **Action** | Type of action (create, update, delete, approve, etc.) |
| **Table** | What entity was affected (users, vehicles, fuel_records, etc.) |
| **Record ID** | The specific record that was changed |
| **Old Values** | Data before the change (JSON format) |
| **New Values** | Data after the change (JSON format) |
| **IP Address** | User's IP address |
| **User Agent** | Browser/device information |
| **Timestamp** | Exact date and time of action |

## Super Admin Activity Logs Interface

Super admins can view **all activity** through the **📜 Activity Logs** tab in the admin dashboard.

### Features:

#### Advanced Filtering
- **By Action Type**: Create, Update, Delete, Approve, Activate, Deactivate, Grant Access, Revoke Access, Login, Logout
- **By Table**: Users, User Roles, Vehicles, Fuel Records, Sections, Section Access
- **By User**: (all users or specific user)
- **Date Range**: (coming soon)

#### Smart Display
- **Color-coded action badges** for quick visual identification
- **Before/After comparison** shows what changed
- **User context** - who made the change and when
- **IP tracking** - see where actions originated

#### Pagination
- View 50, 100, 250, or 500 records per page
- Navigate through historical logs

## Technical Implementation

### Core Component: `AuditLogger` Class

Located at: `/var/www/woodson/maintenance/src/AuditLogger.php`

**Quick Usage:**

```php
use WoodsonISD\Maintenance\AuditLogger;

// Log a creation
AuditLogger::logCreate('vehicles', $vehicleId, [
    'name' => 'Bus #12',
    'vehicle_number' => '12',
    'created_by' => $currentUser['name']
]);

// Log an update
AuditLogger::logUpdate('users', $userId, 
    ['role' => 'staff'],  // Old values
    ['role' => 'admin', 'changed_by' => $currentUser['name']]  // New values
);

// Log a deletion
AuditLogger::logDelete('fuel_records', $recordId, [
    'gallons' => 15.5,
    'odometer_reading' => 45000,
    'deleted_by' => $currentUser['name']
]);

// Log custom actions
$logger = new AuditLogger();
$logger->log(
    'approve',  // action
    'users',    // table
    $userId,    // record ID
    ['is_active' => 0],  // old values
    ['is_active' => 1, 'approved_by' => $currentUser['name']]  // new values
);
```

### Integrated Endpoints

Audit logging is integrated into all API endpoints:

✅ `/public/api/users.php` - User management
✅ `/public/api/user-roles.php` - Multi-role management
✅ `/public/api/fuel-records.php` - Fuel entries
✅ `/public/api/vehicles.php` - Vehicle management
✅ `/public/api/sections.php` - Section management
✅ `/public/api/section-access.php` - Access control
✅ `/src/Auth.php` - Authentication (login/logout)

### Database Schema

Table: `audit_log`

```sql
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INT NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_table (table_name),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

## Security & Privacy

### Access Control
- **Only super admins** can view audit logs
- **No modification allowed** - logs are append-only
- **User deletion** doesn't delete logs (user_id set to NULL, preserving record)

### IP Address Handling
- Respects proxy headers (Cloudflare, X-Forwarded-For)
- Validates IP addresses before storage
- Captures real client IP when behind load balancers

### Error Handling
- Audit failures **never break the main operation**
- Errors logged to PHP error log
- Graceful degradation if audit system fails

## Performance Considerations

### Optimized Queries
- Indexed on: user_id, action, table_name, created_at
- Efficient pagination with LIMIT/OFFSET
- Joins only when displaying (not when logging)

### Minimal Overhead
- Async design - doesn't slow down user operations
- JSON compression for change data
- Selective field capture (only relevant changes)

## Future Enhancements

### Planned Features
1. **Date Range Filtering** - Filter logs by specific date ranges
2. **User-Specific Views** - Filter by who made changes
3. **Export Logs** - Download audit logs as CSV/Excel
4. **Log Retention Policy** - Auto-archive old logs after X days
5. **Real-time Notifications** - Alert admins of critical actions
6. **Change Comparison UI** - Side-by-side before/after view
7. **Restore from Log** - Undo changes using audit history

### Additional Log Types (When Features Added)
- Travel request submissions/approvals
- Reimbursement claims/payments
- Maintenance work orders
- Inventory adjustments
- Document uploads
- Report generation
- System configuration changes

## Compliance & Auditing

This audit logging system supports:

- **Accountability** - Know who made every change
- **Compliance** - Meet state/federal record-keeping requirements
- **Forensics** - Investigate issues or disputes
- **Training** - Identify areas where users need help
- **Security** - Detect unauthorized access or suspicious activity

## Testing the System

### Manual Test

1. Log in as super admin
2. Go to Admin Dashboard → Activity Logs tab
3. You should see your login event logged
4. Create a test fuel entry - see it logged
5. Edit a vehicle - see the before/after values
6. Filter by action type or table
7. Check pagination

### Database Verification

```bash
# View recent logs
mysql -u WISDAdmin -p woodson_maintenance -e "
  SELECT user_id, action, table_name, record_id, created_at 
  FROM audit_log 
  ORDER BY created_at DESC 
  LIMIT 10;"

# Count logs by action type
mysql -u WISDAdmin -p woodson_maintenance -e "
  SELECT action, COUNT(*) as count 
  FROM audit_log 
  GROUP BY action 
  ORDER BY count DESC;"

# View logs for specific user
mysql -u WISDAdmin -p woodson_maintenance -e "
  SELECT * FROM audit_log 
  WHERE user_id = 1 
  ORDER BY created_at DESC;"
```

## Troubleshooting

### No logs appearing?
1. Check if `audit_log` table exists
2. Verify user has permissions: `GRANT ALL ON woodson_maintenance.* TO 'WISDAdmin'@'localhost';`
3. Check PHP error log: `tail -f /var/log/apache2/error.log`
4. Ensure AuditLogger class is loaded in bootstrap

### Logs tab not visible?
- Must be logged in as **super_admin** role
- Check `user_global_roles` table for super_admin role

### Slow performance?
- Add more indexes if filtering by custom fields
- Consider archiving old logs (>90 days)
- Check table size: `SELECT COUNT(*) FROM audit_log;`

## Support

For questions or issues with audit logging:
- **Super Admin**: richard.sullivan@woodsonisd.net
- **Documentation**: This file
- **Error Logs**: `/var/log/apache2/error.log`
- **Database**: woodson_maintenance.audit_log table

---

**Nothing goes unseen.** Every action, every change, every login - all captured for accountability and compliance.
