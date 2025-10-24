# User Invitation & Approval System

## Overview

The Woodson ISD Vehicle Maintenance application includes a dual-path user onboarding system:

1. **Proactive Invitations**: Admins can invite specific users via email
2. **Self-Registration with Approval**: Any @woodsonisd.net user can attempt login but requires admin approval

## How It Works

### For Administrators

#### Sending Invitations

1. Log in as Super Admin or Maintenance Director
2. Navigate to the **Invitations** tab in the admin dashboard
3. Click **Send Invitation** button
4. Enter:
   - Email address (must be @woodsonisd.net)
   - Default role (User, Maintenance Director, or Super Admin)
5. Click **Send Invitation**

The system will:
- Generate a secure invitation token
- Send an email to the invited user with a link
- Set invitation expiration to 7 days

#### Approving Pending Users

1. Navigate to the **Pending Users** tab
2. Review users who have attempted to log in but don't have active accounts
3. Click **✓ Approve** to grant access
4. Click **✗ Deny** to permanently reject and delete the request

When you approve a user:
- Their account is activated
- They receive an email notification
- They can immediately access the system

### For Invited Users

1. Check your @woodsonisd.net email for an invitation
2. Click the invitation link (valid for 7 days)
3. You'll be redirected to Google OAuth login
4. Sign in with your @woodsonisd.net Google account
5. Your account is automatically activated with the assigned role
6. You can immediately start using the system

### For Self-Registering Users

1. Visit https://maintenance.woodsonisd.net
2. Click **Sign in with Google**
3. Authenticate with your @woodsonisd.net Google account
4. If you haven't been invited, you'll see: "Your account is pending approval. You will receive an email once approved."
5. Wait for an administrator to approve your request
6. Once approved, you'll receive an email notification
7. Log in again and start using the system

## Technical Details

### Database Schema

#### `invitations` Table
```sql
- id: Primary key
- email: Invited email address
- invited_by: User ID who sent invitation
- role: Default role to assign
- token: Secure 64-character token
- expires_at: Invitation expiration date (7 days)
- used_at: Timestamp when invitation was accepted
- created_at: When invitation was sent
```

#### `users` Table (Invitation Fields)
```sql
- invited_by: User ID who invited this user (NULL if self-registered)
- invited_at: When the invitation was sent
- approved_by: User ID who approved pending user
- approved_at: When pending user was approved
- is_active: Boolean - false for pending users
```

### Authentication Flow

#### Invited User Flow
1. User clicks invitation link → `accept-invite.php`
2. System validates token and expiration
3. Invitation info stored in session
4. Redirect to Google OAuth
5. After OAuth, `Auth::getOrCreateUser()` checks for invitation
6. If invitation found: user created with `is_active=true` and assigned role
7. Invitation marked as `used_at=NOW()`

#### Self-Registration Flow
1. User visits site → clicks Google sign-in
2. Google OAuth authentication
3. `Auth::getOrCreateUser()` checks for invitation
4. No invitation found: user created with `is_active=false`, role='user'
5. User sees "pending approval" message
6. Admin approves in dashboard
7. User receives approval email
8. User logs in successfully

### API Endpoints

#### `/api/invitations.php`
- **GET**: List all invitations (Super Admin only)
- **POST**: Send new invitation (Super Admin/Maintenance Director)
  - Requires: `email`, `role`, `csrf_token`
  - Validates @woodsonisd.net domain
  - Generates secure token
  - Sends invitation email

#### `/api/users.php`
- **GET**: List all users
  - `?pending=true`: List only pending users
- **PUT**: Update user
  - `action=approve`: Approve pending user (Super Admin only)
  - `action=change_role`: Change user role
  - `action=activate/deactivate`: Toggle user status
- **DELETE**: Delete user (deny pending request)

### Email Notifications

#### Invitation Email
```
Subject: You're invited to Woodson ISD Vehicle Maintenance

You've been invited to access the Woodson ISD Vehicle Maintenance system.

Click the link below to accept and set up your account:
[Invitation Link]

This invitation expires in 7 days.
```

#### Approval Email
```
Subject: Your Woodson ISD Maintenance Account Has Been Approved

Your account has been approved! You can now log in and start using the system.

Visit: https://maintenance.woodsonisd.net

Sign in with your @woodsonisd.net Google account.
```

### Security Features

1. **Domain Restriction**: Only @woodsonisd.net emails allowed
2. **Secure Tokens**: 64-character random tokens (256 bits of entropy)
3. **Token Expiration**: Invitations expire after 7 days
4. **One-Time Use**: Tokens marked as used after acceptance
5. **CSRF Protection**: All invitation/approval actions require CSRF tokens
6. **Role Verification**: Only Super Admins can approve users
7. **Self-Modification Prevention**: Users cannot modify their own accounts

## Configuration

### Environment Variables

```env
APP_URL=https://maintenance.woodsonisd.net
SUPER_ADMIN_EMAIL=richard.sullivan@woodsonisd.net
```

### Email Configuration

The system uses PHP's `mail()` function. Ensure your server is configured to send email:

```bash
# Install and configure Postfix or similar MTA
sudo apt-get install postfix
```

For production, consider using SMTP relay (e.g., SendGrid, AWS SES) by updating `Invitation::sendInvitationEmail()` and `Invitation::sendApprovalEmail()`.

## Troubleshooting

### Invitation email not received
- Check spam/junk folders
- Verify server mail configuration: `php -r "mail('test@woodsonisd.net', 'Test', 'Test');"`
- Check mail logs: `sudo tail -f /var/log/mail.log`

### "Invalid or expired invitation"
- Token may have expired (7 days)
- Token may have already been used
- Admin can resend invitation

### User stuck in pending status
- Check **Pending Users** tab in admin dashboard
- Admin must manually approve
- Check for errors in approval action

### Cannot send invitation
- Verify email is @woodsonisd.net
- Check if user already exists
- Check if active invitation already exists for that email

## Best Practices

1. **Invite proactively**: Send invitations to known staff before they need access
2. **Regular review**: Check pending users weekly to approve legitimate requests
3. **Revoke access**: Deactivate users who leave the district
4. **Role assignment**: Default to 'user' role, elevate privileges as needed
5. **Communication**: Inform staff about the system and how to request access

## Future Enhancements

- Bulk invitation upload (CSV)
- Invitation templates
- Automatic expiration cleanup
- Invitation analytics (sent, accepted, expired)
- Configurable expiration periods
- SMS notifications (optional)
- Integration with district LDAP/Active Directory

---

**Last Updated**: December 2024  
**Maintained By**: Woodson ISD IT Department
