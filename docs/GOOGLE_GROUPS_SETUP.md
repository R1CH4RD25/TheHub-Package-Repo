# Google Groups Auto-Approval Setup Guide

## Overview

The system can automatically approve users who are members of specific Google Groups. When a user logs in for the first time, the system checks if they're in the configured staff group. If yes, they're automatically activated with the "staff" role.

## Required Steps

### 1. Enable Google Admin SDK in Google Cloud Console

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Select your project (the one with your OAuth credentials)
3. Navigate to **APIs & Services** > **Library**
4. Search for **"Admin SDK API"**
5. Click **Enable**

### 2. Set up Domain-Wide Delegation (Service Account)

Since checking group membership requires admin access, you need either:

**Option A: Use a Service Account with Domain-Wide Delegation (Recommended)**

1. Go to **IAM & Admin** > **Service Accounts**
2. Create a new service account or use existing one
3. Grant it **"Admin SDK"** permissions
4. Enable **Domain-Wide Delegation**
5. Download the JSON key file
6. In Google Workspace Admin:
   - Go to **Security** > **API Controls** > **Domain-wide Delegation**
   - Add the service account client ID
   - Grant scope: `https://www.googleapis.com/auth/admin.directory.group.readonly`

**Option B: Use User OAuth with Admin Account**

The current implementation uses the logged-in user's access token. For this to work:
- The OAuth consent screen must be set to **"Internal"** (Woodson ISD only)
- Users must grant consent for the Admin SDK scope
- **This only works if the logging-in user is a Google Workspace Admin**

### 3. Update OAuth Consent Screen

1. Go to **APIs & Services** > **OAuth consent screen**
2. Click **Edit App**
3. Under **Scopes**, add:
   - `https://www.googleapis.com/auth/admin.directory.group.readonly`
4. Save changes

### 4. Configure the Staff Group Email

In your `.env` file, set the group email:

```env
STAFF_GROUP_EMAIL=staff@woodsonisd.net
```

Replace `staff@woodsonisd.net` with your actual Google Group email address.

### 5. Create the Google Group

In Google Workspace Admin:
1. Go to **Directory** > **Groups**
2. Create a new group (or use existing):
   - **Name**: Staff
   - **Email**: staff@woodsonisd.net
   - **Description**: Auto-approved staff members
3. Add members to the group

## How It Works

### Login Flow with Google Groups Check:

1. User clicks "Sign in with Google"
2. Google prompts for permissions (including Groups API)
3. User authenticates and grants permissions
4. System receives access token
5. System calls `checkGoogleGroupMembership()`:
   - Makes API call to: `https://www.googleapis.com/admin/directory/v1/groups/{groupEmail}/hasMember/{userEmail}`
   - Checks if user is in the configured group
6. Based on result:
   - **In group**: User auto-approved as "staff" (active)
   - **Not in group**: User created as pending (inactive, needs admin approval)

### Code Implementation

Located in `src/Auth.php`:

```php
private function checkGoogleGroupMembership($accessToken, $userEmail)
{
    $groupEmail = $_ENV['STAFF_GROUP_EMAIL'] ?? null;
    $groupsUrl = "https://www.googleapis.com/admin/directory/v1/groups/{$groupEmail}/hasMember/{$userEmail}";
    
    // Makes API call with user's access token
    // Returns true if user is member, false otherwise
}
```

## Testing

### Test the Setup:

1. **Clear sessions**: `rm -f sessions/sess_*`
2. **Test with group member**:
   - Add a test user to the Google Group
   - Have them log in
   - Check logs: Should see "Auto-approved {email} as staff (Google Groups member)"
   - User should have immediate access
3. **Test with non-member**:
   - Have someone NOT in the group log in
   - Check logs: Should see "Created pending user {email} (not in staff group, needs approval)"
   - User should see "pending approval" message

### Check Logs:

```bash
tail -f /var/www/woodson/maintenance/logs/php-errors.log | grep "Google Groups"
```

You should see:
```
Google Groups check for user@woodsonisd.net in staff@woodsonisd.net: YES
Auto-approved user@woodsonisd.net as staff (Google Groups member)
```

## Troubleshooting

### Error: "Google Groups API failed (HTTP 403)"

**Cause**: Missing permissions or Domain-Wide Delegation not set up

**Fix**:
1. Verify Admin SDK API is enabled
2. Check OAuth scopes include `admin.directory.group.readonly`
3. Ensure service account has Domain-Wide Delegation (if using service account)
4. For user OAuth: User must be a Google Workspace Admin

### Error: "STAFF_GROUP_EMAIL not configured"

**Fix**: Add `STAFF_GROUP_EMAIL=your-group@woodsonisd.net` to `.env` file

### Error: "Failed to get access token"

**Fix**: 
1. User declined permissions during OAuth consent
2. OAuth app not verified (use Internal app type)
3. Check Google Cloud Console credentials

### Users Not Auto-Approved

**Checklist**:
1. ✅ User is actually in the Google Group
2. ✅ Group email matches `STAFF_GROUP_EMAIL` in `.env`
3. ✅ Admin SDK API is enabled
4. ✅ OAuth scopes include Groups API
5. ✅ User granted consent during login
6. ✅ Check error logs for API failures

## Security Notes

- ✅ Only @woodsonisd.net emails can log in (domain restriction)
- ✅ Group membership checked on FIRST login only
- ✅ Access token not stored (only used during authentication)
- ✅ Manual approval still required for non-group members
- ✅ Existing invited users bypass group check (invitation takes precedence)

## Environment Variables

```env
# Google OAuth
GOOGLE_CLIENT_ID=your-client-id
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=https://maintenance.woodsonisd.net/google_login.php

# Admin
SUPER_ADMIN_EMAIL=richard.sullivan@woodsonisd.net

# Auto-Approval via Google Groups
STAFF_GROUP_EMAIL=staff@woodsonisd.net
```

## Next Steps

After setup, you can:
1. Add/remove users from the Google Group to control auto-approval
2. Create multiple groups for different roles (future enhancement)
3. Monitor group membership changes in Google Workspace Admin

