# Microsoft OAuth Integration

## Overview
The Hub now supports Microsoft OAuth (Azure AD) authentication alongside Google OAuth. Organizations can enable Microsoft login, use both providers simultaneously, or switch between them.

## Configuration

### Environment Variables (.env)

```bash
# Enable/disable Microsoft login
ENABLE_MICROSOFT_LOGIN=false

# Azure AD App Registration details
MICROSOFT_CLIENT_ID=your-app-id-here
MICROSOFT_CLIENT_SECRET=your-client-secret-here
MICROSOFT_TENANT_ID=common
MICROSOFT_REDIRECT_URI=https://hub.yourdomain.com/microsoft_login.php
```

### Admin Panel Configuration

Navigate to: **Admin Dashboard → Site Settings → Advanced**

Look for the **"Microsoft OAuth Configuration (Optional)"** section with these fields:

1. **Enable Microsoft Login** - Toggle checkbox
2. **Microsoft Application (Client) ID** - From Azure Portal
3. **Microsoft Client Secret** - Secret value from Azure
4. **Microsoft Tenant ID** - Use "common" for multi-tenant or your org's tenant ID
5. **Microsoft Redirect URI** - Must match Azure Portal configuration

## Azure AD Setup Instructions

### Step 1: Create App Registration

1. Go to [Azure Portal - App Registrations](https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade)
2. Click **"+ New registration"**
3. Enter details:
   - **Name**: The Hub (or your app name)
   - **Supported account types**: 
     - Choose "Accounts in any organizational directory (Any Azure AD directory - Multitenant)" for multi-tenant
     - Or "Accounts in this organizational directory only" for single-tenant
   - **Redirect URI**: 
     - Platform: Web
     - URI: `https://hub.yourdomain.com/microsoft_login.php`
4. Click **Register**

### Step 2: Copy Application ID

1. After registration, you'll see the **Overview** page
2. Copy the **Application (client) ID** (GUID format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)
3. Copy the **Directory (tenant) ID** (or use "common" for multi-tenant)

### Step 3: Create Client Secret

1. Go to **Certificates & secrets** in the left menu
2. Click **"+ New client secret"**
3. Add description: "The Hub OAuth"
4. Select expiration (recommendation: 24 months)
5. Click **Add**
6. **IMPORTANT**: Copy the **Value** immediately (it won't be shown again)

### Step 4: Configure API Permissions

1. Go to **API permissions** in the left menu
2. Click **"+ Add a permission"**
3. Select **Microsoft Graph**
4. Select **Delegated permissions**
5. Add these permissions:
   - `User.Read` (should be added by default)
   - `email`
   - `openid`
   - `profile`
6. Click **Add permissions**
7. (Optional) Click **"Grant admin consent"** if you're a tenant admin

### Step 5: Configure Redirect URIs

1. Go to **Authentication** in the left menu
2. Under **Platform configurations → Web**, verify your redirect URI:
   - `https://hub.yourdomain.com/microsoft_login.php`
3. Under **Implicit grant and hybrid flows**, enable:
   - ✅ ID tokens (used for implicit and hybrid flows)
4. Click **Save**

## Usage Scenarios

### Scenario 1: Microsoft Only
```bash
ENABLE_MICROSOFT_LOGIN=true
GOOGLE_ONLY_LOGIN=false
ALLOW_LOCAL_USERS=false
```
Users see only "Sign in with Microsoft" button

### Scenario 2: Both Google and Microsoft
```bash
ENABLE_MICROSOFT_LOGIN=true
GOOGLE_ONLY_LOGIN=false
ALLOW_LOCAL_USERS=false
```
Users see both "Sign in with Google" and "Sign in with Microsoft" buttons

### Scenario 3: Google Primary, Microsoft Optional
```bash
ENABLE_MICROSOFT_LOGIN=true
GOOGLE_ONLY_LOGIN=true
ALLOW_LOCAL_USERS=false
```
Users see both options, but Google is the default/primary

## Tenant ID Options

### Multi-Tenant (common)
```bash
MICROSOFT_TENANT_ID=common
```
- Allows users from ANY Azure AD organization
- Use for public/SaaS applications
- Users from gmail.com, outlook.com, etc. can also sign in

### Single-Tenant
```bash
MICROSOFT_TENANT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
```
- Replace with your organization's tenant ID
- Only allows users from your specific Azure AD organization
- More secure for internal applications

### Organizations Only
```bash
MICROSOFT_TENANT_ID=organizations
```
- Allows users from any Azure AD organization
- Blocks personal Microsoft accounts (outlook.com, hotmail.com, etc.)

### Consumers Only
```bash
MICROSOFT_TENANT_ID=consumers
```
- Only allows personal Microsoft accounts
- Blocks organizational accounts

## Implementation Notes

### Current Status
- ✅ Environment variables configured
- ✅ Admin UI added
- ✅ Backend API updated (system-config.php)
- ⚠️ Frontend login page needs update (microsoft_login.php not yet created)
- ⚠️ Auth.php needs Microsoft OAuth handler

### Next Steps for Full Implementation

1. **Create microsoft_login.php** - OAuth callback handler
2. **Update Auth.php** - Add Microsoft OAuth methods similar to Google
3. **Update login.php** - Add "Sign in with Microsoft" button
4. **Install Microsoft Graph SDK** (optional):
   ```bash
   composer require microsoft/microsoft-graph
   ```

### Security Considerations

1. **Client Secret Rotation**: Secrets expire (max 24 months in Azure)
   - Set reminder to rotate before expiration
   - Update .env when rotating

2. **Redirect URI Validation**: Must match exactly
   - Include in Azure Portal
   - Include in .env MICROSOFT_REDIRECT_URI

3. **Tenant Restrictions**: 
   - Use specific tenant ID for internal apps
   - Use "common" only if you need multi-tenant

4. **API Permissions**: 
   - Only request needed scopes
   - Get admin consent for organization-wide deployment

## Testing

1. **Enable in Admin Panel**:
   - Go to Advanced Settings
   - Enable Microsoft Login
   - Enter your Azure AD credentials
   - Save settings

2. **Verify .env updated**:
   ```bash
   grep MICROSOFT /var/www/woodson/thehub/.env
   ```

3. **Check API loads correctly**:
   - Open browser DevTools
   - Go to Advanced Settings tab
   - Check Network tab for `system-config.php?action=load`
   - Verify `microsoft_oauth` section in response

## Troubleshooting

### "AADSTS50011: Redirect URI mismatch"
- Verify MICROSOFT_REDIRECT_URI matches exactly what's in Azure Portal
- Check for trailing slashes, http vs https, etc.

### "AADSTS700016: Application not found"
- MICROSOFT_CLIENT_ID is incorrect
- App registration was deleted

### "Invalid client secret"
- MICROSOFT_CLIENT_SECRET is wrong or expired
- Generate new secret in Azure Portal

### "AADSTS50020: User account from identity provider does not exist"
- Tenant ID restricts which users can sign in
- Check MICROSOFT_TENANT_ID setting

## References

- [Azure AD OAuth 2.0 Docs](https://docs.microsoft.com/en-us/azure/active-directory/develop/v2-oauth2-auth-code-flow)
- [Microsoft Identity Platform](https://docs.microsoft.com/en-us/azure/active-directory/develop/)
- [App Registration Portal](https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade)
