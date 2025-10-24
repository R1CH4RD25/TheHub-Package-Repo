# User Invitation & Approval System - Implementation Summary

## What Was Built

A complete user invitation and approval system has been added to the Woodson ISD Vehicle Maintenance application, providing administrators with two ways to onboard users:

1. **Proactive Email Invitations** - Send invitation emails with secure links
2. **Approval Workflow** - Review and approve users who self-register

## Files Created/Modified

### New Files Created

1. **`src/Invitation.php`** (116 lines)
   - Class for managing user invitations
   - Methods: `create()`, `getByToken()`, `markUsed()`, `getAll()`
   - Email sending: `sendInvitationEmail()`, `sendApprovalEmail()`

2. **`public/api/invitations.php`** (49 lines)
   - REST API endpoint for invitation management
   - GET: List all invitations
   - POST: Send new invitation

3. **`public/accept-invite.php`** (66 lines)
   - Invitation acceptance page
   - Validates token and expiration
   - Redirects to Google OAuth with invitation context

4. **`INVITATION_SYSTEM.md`** (Comprehensive documentation)
   - User guide for admins and end-users
   - Technical documentation
   - API reference
   - Troubleshooting guide

### Modified Files

1. **`database/schema.sql`**
   - Added `invitations` table with columns:
     - `id`, `email`, `invited_by`, `role`, `token`, `expires_at`, `used_at`, `created_at`
   - Added invitation tracking columns to `users` table:
     - `invited_by`, `invited_at`, `approved_by`, `approved_at`

2. **`src/Auth.php`**
   - Modified `getOrCreateUser()` to check for invitations
   - Invited users: activated immediately with invited role
   - Non-invited users: created as inactive (pending approval)
   - Updated error messages to distinguish pending vs deactivated users

3. **`src/User.php`**
   - Added `approve($id, $approvedBy)` method
   - Added `getPending()` method to get inactive users
   - Added `delete($id)` method for denying requests

4. **`public/api/users.php`**
   - Added `?pending=true` GET parameter for pending users
   - Added `action=approve` PUT action for approving users
   - Added DELETE endpoint for denying/deleting users
   - Sends approval email notification

5. **`public/admin/index.php`**
   - Added **Pending Users** tab (Super Admin only)
   - Added **Invitations** tab (Super Admin only)
   - Added invitation modal with form
   - Updated menu navigation

6. **`public/assets/js/admin.js`** (+180 lines)
   - `loadPendingUsers()` - Display pending user approvals
   - `approveUser(id)` - Approve pending user
   - `denyUser(id)` - Deny and delete user request
   - `loadInvitations()` - Display sent invitations
   - `showInvitationModal()` - Open invitation form
   - `handleInvitationSubmit()` - Send invitation via API

7. **`public/assets/css/admin.css`**
   - Added `.btn-sm` for smaller buttons
   - Added `.btn-danger` for deny actions
   - Added form helper text styling

## Database Changes

### New Table: `invitations`
```sql
CREATE TABLE invitations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    invited_by INT NOT NULL,
    role ENUM('user', 'maintenance_director', 'super_admin'),
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invited_by) REFERENCES users(id)
);
```

### Modified Table: `users`
Added columns:
- `invited_by INT NULL` - Who invited this user
- `invited_at DATETIME NULL` - When invitation was sent
- `approved_by INT NULL` - Who approved pending user
- `approved_at DATETIME NULL` - When user was approved

## User Flows

### Admin Sending Invitation
1. Admin clicks **Invitations** tab → **Send Invitation**
2. Enters email (@woodsonisd.net) and role
3. System generates secure token, sends email
4. User clicks link in email
5. `accept-invite.php` validates token
6. Redirects to Google OAuth
7. User logs in with Google
8. Account created as active with invited role
9. Invitation marked as used

### Admin Approving Pending User
1. User attempts login without invitation
2. Account created as inactive (pending)
3. User sees "pending approval" message
4. Admin sees request in **Pending Users** tab
5. Admin clicks **✓ Approve**
6. User account activated
7. Approval email sent to user
8. User can now log in

## Security Features

✅ **Domain Restriction** - Only @woodsonisd.net emails  
✅ **Secure Tokens** - 64-character random tokens (256-bit entropy)  
✅ **Token Expiration** - 7-day expiration on invitations  
✅ **One-Time Use** - Tokens invalidated after use  
✅ **CSRF Protection** - All mutations require CSRF tokens  
✅ **Role-Based Access** - Only Super Admin can approve users  
✅ **Self-Modification Prevention** - Users cannot modify own account  

## Testing Checklist

- [ ] Super admin can access Invitations tab
- [ ] Super admin can access Pending Users tab
- [ ] Send invitation modal opens and validates @woodsonisd.net
- [ ] Invitation email sent with valid link
- [ ] Clicking invitation link validates token
- [ ] Expired invitations show error message
- [ ] Used invitations cannot be reused
- [ ] Self-registered user appears in Pending Users
- [ ] Approving user activates account and sends email
- [ ] Denying user deletes from database
- [ ] Regular users cannot access invitation features
- [ ] CSRF protection prevents unauthorized actions

## Email Configuration Required

The system uses PHP's `mail()` function. For production use:

1. **Option A: Local Mail Server**
   ```bash
   sudo apt-get install postfix
   # Configure as Internet Site
   ```

2. **Option B: SMTP Relay (Recommended)**
   - Update `Invitation::sendInvitationEmail()`
   - Update `Invitation::sendApprovalEmail()`
   - Use PHPMailer or similar library
   - Configure SMTP credentials (e.g., SendGrid, AWS SES)

## Next Steps

1. **Test Email Delivery**
   ```bash
   php -r "mail('richard.sullivan@woodsonisd.net', 'Test', 'Test email');"
   ```

2. **Review Mail Logs**
   ```bash
   sudo tail -f /var/log/mail.log
   ```

3. **Test Complete Flow**
   - Send invitation to test email
   - Attempt self-registration
   - Verify both paths work correctly

4. **Monitor Pending Users**
   - Check dashboard weekly
   - Approve legitimate requests
   - Deny suspicious activity

## Architecture Decisions

### Why Two Onboarding Paths?

1. **Invitations** - Proactive, known users, immediate access
2. **Approval** - Reactive, unknown users, controlled access

### Why 7-Day Expiration?

Balance between:
- User convenience (enough time to check email)
- Security (limited window for token exposure)
- Admin overhead (not too many expired invitations)

### Why Email Validation?

- Ensures only district staff have access
- Prevents accidental external access
- Aligns with Google OAuth domain restriction

## Known Limitations

1. **No Bulk Invitations** - Must invite users one at a time
2. **Basic Email Templates** - Plain text emails
3. **No Invitation Resend** - Must send new invitation if expired
4. **No Invitation Revocation** - Cannot cancel sent invitations
5. **No Notification System** - Admins must check for pending users

## Future Enhancements

- Bulk invitation upload (CSV)
- Rich HTML email templates
- Invitation analytics dashboard
- Automatic cleanup of expired invitations
- Push notifications for pending approvals
- Integration with district LDAP/Active Directory
- Configurable expiration periods
- Invitation templates by role

---

**Implementation Date**: December 2024  
**Developer**: GitHub Copilot  
**Status**: ✅ Complete and Ready for Testing
