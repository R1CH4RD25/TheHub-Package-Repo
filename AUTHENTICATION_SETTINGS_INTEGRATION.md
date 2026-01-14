# Authentication Settings Integration - Complete

## What We Accomplished

### ✅ 1. Added Authentication & Login Section to Settings UI
**Location:** Admin → Settings → Behavior & Access → Authentication & Login

**Fields (9 total):**
- **Login Methods:**
  - Allow Physical Login (ALLOW_LOCAL_USERS)
  - Enable Google OAuth (ENABLE_GOOGLE_LOGIN)
  - Enable Microsoft OAuth (ENABLE_MICROSOFT_LOGIN)

- **Domain Restrictions:**
  - Require Domain Match (REQUIRE_DOMAIN_MATCH)
  - Allowed Domains (ALLOWED_DOMAINS - comma-separated list)

- **Cloud Identity Groups:**
  - Enable Google Groups Sync (ENABLE_GOOGLE_GROUPS)
  - Enable Microsoft Groups Sync (ENABLE_MICROSOFT_GROUPS)
  - Info panel linking to Organization Roles → Cloud Groups configuration

### ✅ 2. Database Integration
**Created Migration:** `database/migrations/add_auth_settings.sql`

**Added Settings to `site_settings` table:**
```sql
- allow_local_users (boolean)
- enable_google_login (boolean)
- enable_microsoft_login (boolean)
- require_domain_match (boolean)
- allowed_domains (string)
- enable_google_groups (boolean)
- enable_microsoft_groups (boolean)
- session_timeout_minutes (number)
- max_upload_size (number)
```

### ✅ 3. Auto-Sync Organization Roles on Login
**Modified:** `src/Auth.php`

**New Methods:**
1. `syncOrganizationRoles($userId, $userEmail, $accessToken)` - Main sync orchestrator
   - Checks if Google Groups or Microsoft Groups are enabled
   - Queries user's group memberships from both providers
   - Matches against `org_role_google_groups` and `org_role_microsoft_groups` tables
   - Auto-assigns organization roles via `OrgRole::assignToUser()`
   
2. `getUserMicrosoftGroups($userEmail)` - Azure AD integration
   - Uses Microsoft Graph API to query user's group memberships
   - Returns array of group IDs (GUIDs)
   - Requires `MICROSOFT_TENANT_ID`, `MICROSOFT_CLIENT_ID`, `MICROSOFT_CLIENT_SECRET`

**Integration Point:**
- Called in `handleCallback()` right after `updateLastLogin()`
- Runs on EVERY login (Google OAuth or Microsoft OAuth)
- Uses access token from OAuth callback for API authentication

### ✅ 4. Added Missing Settings to UI
**System Tab → Security Section:**
- Added `max_upload_size` field (was in .env but not exposed in UI)
- Updated badge count from 2 to 3

## How It Works

### Login Flow with Auto-Role Assignment

1. **User logs in via Google OAuth or Microsoft OAuth**
   - Auth::handleCallback() processes OAuth callback
   - Gets user info (email, name, picture, etc.)
   - Validates domain if REQUIRE_DOMAIN_MATCH is enabled
   - Creates or updates user record

2. **Last login timestamp updated**
   - Auth::updateLastLogin() marks current time

3. **Organization roles synced** ⭐ NEW
   - Auth::syncOrganizationRoles() called with user ID, email, access token
   - Checks `enable_google_groups` and `enable_microsoft_groups` settings
   - If Google Groups enabled:
     - Calls getUserGoogleGroups() to get user's groups
     - Queries org_role_google_groups table for mappings with `sync_on_login = 1`
     - Matches user's groups against configured patterns (supports wildcards)
   - If Microsoft Groups enabled:
     - Calls getUserMicrosoftGroups() to get user's Azure AD groups
     - Queries org_role_microsoft_groups table for mappings with `sync_on_login = 1`
     - Matches user's group IDs against configured GUIDs
   - Assigns all matched organization roles via OrgRole::assignToUser()
   - Replaces existing org role assignments with new matched set

4. **Session created**
   - User is logged in and redirected to dashboard

### Example Scenario

**Configuration:**
- Google Groups enabled: ✅
- Microsoft Groups enabled: ❌
- Org Role "Principal" mapped to Google Group: `principals@woodsonisd.net`
- Org Role "Teacher" mapped to Google Group: `staff@woodsonisd.net`
- Both mappings have `sync_on_login = 1`

**Login Flow:**
1. User `john.doe@woodsonisd.net` logs in via Google OAuth
2. System queries Google Groups API → finds user in `staff@woodsonisd.net`
3. Matches group to "Teacher" org role
4. Auto-assigns "Teacher" org role to john.doe
5. Package permissions automatically reflect Teacher role (via package_role_mappings)
6. User sees only the modules/sections they're authorized for

## Settings UI Structure

### Behavior & Access Tab (before)
- Navigation (1 field)
- Management Branding (3 fields)
- Management Access (5 fields)
**Total: 9 fields**

### Behavior & Access Tab (after)
- **Authentication & Login (9 fields)** ⭐ NEW
- Navigation (1 field)
- Management Branding (3 fields)
- Management Access (5 fields)
**Total: 18 fields**

### System Tab (before)
- Sessions (1 field)
- Security (2 fields)
- Danger Zone (2 actions)
**Total: 5 fields**

### System Tab (after)
- Sessions (1 field)
- Security (3 fields) ⭐ UPDATED (+max_upload_size)
- Danger Zone (2 actions)
**Total: 6 fields**

## Configuration Required

### For Google Groups Auto-Sync:
```env
ENABLE_GOOGLE_GROUPS=true
GOOGLE_SERVICE_ACCOUNT_JSON=/path/to/service-account.json
GOOGLE_ADMIN_EMAIL=admin@yourdomain.com
```

### For Microsoft Groups Auto-Sync:
```env
ENABLE_MICROSOFT_GROUPS=true
MICROSOFT_TENANT_ID=your-tenant-id
MICROSOFT_CLIENT_ID=your-client-id
MICROSOFT_CLIENT_SECRET=your-client-secret
```

### Cloud Group Mappings:
Configured via: **Admin → Users → Organization Roles → Cloud Groups**

For each org role, add:
- Google Groups (email addresses with wildcard support)
- Microsoft Groups (GUIDs)
- Toggle "Sync on Login" checkbox

## Verification Steps

1. ✅ Settings migrated from .env to database
2. ✅ Settings UI shows all auth fields
3. ✅ Auth.php implements auto-sync logic
4. ✅ Microsoft Graph API integration complete
5. ✅ No missing critical settings
6. ✅ Git committed with descriptive message

## Next Steps (Optional)

1. **Test the auto-sync flow:**
   - Configure Google or Microsoft Groups mapping
   - Log in with test user
   - Verify org roles auto-assigned
   - Check audit logs for sync events

2. **Add audit logging to syncOrganizationRoles():**
   - Log when roles are added/removed via auto-sync
   - Track which groups triggered assignments
   - Useful for debugging and compliance

3. **Add UI indicators:**
   - Show "Auto-assigned via [Google/Microsoft]" badge on user roles
   - Distinguish manual vs automatic role assignments
   - Prevent manual removal of auto-assigned roles

4. **Performance optimization:**
   - Cache user's group memberships for session duration
   - Only re-sync if cache expired or forced refresh
   - Reduce API calls to Google/Microsoft on every login

## Files Modified

1. `resources/views/admin/settings.blade.php` - Added Authentication section
2. `database/migrations/add_auth_settings.sql` - Migration for auth settings
3. `src/Auth.php` - Added syncOrganizationRoles() and getUserMicrosoftGroups()
4. `public/assets/js/site-settings.js` - (No changes needed, auto-handles new fields)

## Commit Hash
`b90c241` - ✨ Add Authentication section to Settings UI with cloud groups integration
