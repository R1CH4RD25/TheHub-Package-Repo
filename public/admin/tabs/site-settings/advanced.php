                        <div id="subtab-advanced" class="user-subtab">

                            <!-- Critical Warning Banner -->
                            <div class="settings-section" style="border: 2px solid #DC2626; background: #FEF2F2; margin-bottom: 1.5rem;">
                                <h2 style="color: #DC2626;">Super Admin Only - System Configuration</h2>
                                <p style="color: #991B1B; margin-bottom: 0; font-weight: 600;">
                                    WARNING: These settings control core system functionality. Unauthorized changes can break the application.
                                    All modifications are logged and require your Super Admin credentials.
                                </p>
                            </div>

                            <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                                Click sections below to expand and configure system settings. Changes are saved to your <code>.env</code> file with automatic backup.
                            </p>

                            <!-- Authentication & Login -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Authentication & Login
                                        <span class="color-section-badge">5</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                            Configure how users authenticate and access the system
                                        </p>

                                        <h4 style="margin-bottom: 1rem; font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Authentication Methods</h4>
                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <label class="checkbox-label">
                                                    <input type="checkbox" id="allowLocalUsers" style="width: auto; margin-right: 0.5rem;">
                                                    <span>Local User Accounts</span>
                                                </label>
                                                <small>Allow users to sign in with username and password stored in the database.</small>
                                            </div>

                                            <div class="form-group">
                                                <label class="checkbox-label">
                                                    <input type="checkbox" id="enableGoogleLogin" checked style="width: auto; margin-right: 0.5rem;" onchange="toggleAuthSection('google', this.checked)">
                                                    <span>Google OAuth</span>
                                                </label>
                                                <small>Allow users to sign in with their Google accounts. Configure details in "Google OAuth & Groups" below.</small>
                                            </div>

                                            <div class="form-group">
                                                <label class="checkbox-label">
                                                    <input type="checkbox" id="enableMicrosoftLogin" style="width: auto; margin-right: 0.5rem;" onchange="toggleAuthSection('microsoft', this.checked)">
                                                    <span>Microsoft OAuth</span>
                                                </label>
                                                <small>Allow users to sign in with their Microsoft accounts. Configure details in "Microsoft OAuth & Groups" below.</small>
                                            </div>
                                        </div>

                                        <h4 style="margin: 2rem 0 1rem; font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Access Restrictions</h4>
                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <label>
                                                    <input type="checkbox" id="requireDomainMatch" style="width: auto; margin-right: 0.5rem;">
                                                    Require Domain Match
                                                </label>
                                                <small>Only allow Google/Microsoft accounts from specific domain(s). Configure domains below.</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="allowedDomains">Allowed Email Domains</label>
                                                <input type="text" id="allowedDomains" placeholder="example.com, yourdomain.org">
                                                <small>Comma-separated list of allowed email domains for OAuth login</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="sessionTimeout">Session Timeout (hours)</label>
                                                <input type="number" id="sessionTimeout" min="1" max="168" value="2">
                                                <small>How long users stay logged in before requiring re-authentication (1-168 hours)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Google OAuth & Groups -->
                            <div class="color-section" id="googleAuthSection">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Google OAuth & Groups
                                        <span class="color-section-badge">7</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                            Configure Google OAuth login and auto-role assignment based on Google Workspace group membership.
                                            <a href="https://console.cloud.google.com/apis/credentials" target="_blank" style="color: var(--primary-color);">
                                                → Open Google Cloud Console
                                            </a>
                                        </p>
                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <label for="googleClientId">Google Client ID</label>
                                                <input type="text" id="googleClientId" placeholder="xxxxx.apps.googleusercontent.com">
                                                <small>From Google Cloud Console → Credentials → OAuth 2.0 Client ID</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="googleClientSecret">Google Client Secret</label>
                                                <input type="password" id="googleClientSecret" placeholder="GOCSPX-xxxxx">
                                                <small>Secret key from Google Cloud Console OAuth client</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="googleRedirectUri">Google Redirect URI</label>
                                                <input type="text" id="googleRedirectUri" placeholder="https://yourdomain.com/google_login.php">
                                                <small>Must match exactly what's configured in Google Cloud Console</small>
                                            </div>

                                            <!-- Google Groups Auto-Role Assignment -->
                                            <div class="form-group" style="grid-column: 1 / -1; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                                                <h4 style="margin-bottom: 0.5rem; font-size: 1.1rem;">Google Workspace Groups Integration</h4>
                                                <p style="color: var(--text-muted); margin-bottom: 1rem; font-size: 0.9rem;">
                                                    Automatically assign roles based on Google Workspace group membership. Requires a service account.
                                                </p>
                                            </div>

                                            <div class="form-group" style="grid-column: 1 / -1;">
                                                <label class="checkbox-label">
                                                    <input type="checkbox" id="enableGoogleGroups" onchange="toggleDependentSection('enableGoogleGroups', 'googleGroupsFields', true)">
                                                    <span>Enable Google Groups Auto-Role Assignment</span>
                                                </label>
                                                <small>Automatically grant roles based on user's Google Workspace group membership</small>
                                            </div>

                                            <!-- Dependent Google Groups Fields -->
                                            <div id="googleGroupsFields" style="display: none; grid-column: 1 / -1;">
                                                <div class="settings-grid" style="padding-left: 1.5rem; border-left: 3px solid var(--primary-color); margin-top: 1rem;">
                                                    <?php
                                                    // Get primary domain from ALLOWED_DOMAINS for dynamic placeholders
                                                    $allowedDomains = $_ENV['ALLOWED_DOMAINS'] ?? 'yourdomain.com';
                                                    $domains = array_map('trim', explode(',', $allowedDomains));
                                                    $primaryDomain = $domains[0] ?? 'yourdomain.com';
                                                    ?>
                                                    <div class="form-group">
                                                        <label for="googleAdminEmail">Google Admin Email</label>
                                                        <input type="text" id="googleAdminEmail" placeholder="admin@<?php echo htmlspecialchars($primaryDomain); ?>">
                                                        <small>Google Workspace admin email with domain delegation permissions</small>
                                                    </div>

                                                    <div class="form-group" style="grid-column: 1 / -1;">
                                                        <label for="googleGroupRoleAssociations">Group-to-Role Associations</label>
                                                        <div style="display: flex; gap: 0.5rem; align-items: flex-start;">
                                                            <input type="text" id="googleGroupRoleAssociations" placeholder="seniors*@<?php echo htmlspecialchars($primaryDomain); ?>:student|teachers@<?php echo htmlspecialchars($primaryDomain); ?>:staff" style="flex: 1;">
                                                            <button type="button" class="btn-sm" onclick="openGoogleGroupModal()" style="white-space: nowrap;">
                                                                Add Group
                                                            </button>
                                                        </div>
                                                        <small>Format: group@domain.com:role1,role2 | Use * for wildcards (e.g., seniors*@<?php echo htmlspecialchars($primaryDomain); ?> matches seniors2026@<?php echo htmlspecialchars($primaryDomain); ?>, seniors2027@<?php echo htmlspecialchars($primaryDomain); ?>) | Multiple roles: comma-separated | Multiple groups: pipe-separated | Or use "Add Group" button</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Microsoft OAuth & Groups -->
                            <div class="color-section" id="microsoftAuthSection">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Microsoft OAuth & Groups
                                        <span class="color-section-badge">6</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                            Enable Microsoft/Azure AD login and auto-role assignment based on Azure AD group membership.
                                            <a href="https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" style="color: var(--primary-color);">
                                                → Open Azure Portal App Registrations
                                            </a>
                                        </p>

                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <label for="microsoftClientId">Microsoft Application (Client) ID</label>
                                                <input type="text" id="microsoftClientId" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                                                <small>From Azure Portal → App Registrations → Your App → Application (client) ID</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="microsoftClientSecret">Microsoft Client Secret</label>
                                                <input type="password" id="microsoftClientSecret" placeholder="Client secret value">
                                                <small>From Azure Portal → Certificates & secrets → Client secrets</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="microsoftTenantId">Microsoft Tenant ID</label>
                                                <input type="text" id="microsoftTenantId" placeholder="common">
                                                <small>Use "common" for multi-tenant, or your organization's tenant ID for single-tenant</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="microsoftRedirectUri">Microsoft Redirect URI</label>
                                                <input type="text" id="microsoftRedirectUri" placeholder="https://hub.example.com/microsoft_login.php">
                                                <small>Must match exactly what's configured in Azure Portal redirect URIs</small>
                                            </div>
                                        </div>

                                        <!-- Azure AD Groups Auto-Role Assignment -->
                                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                                            <h4 style="margin-bottom: 0.5rem; font-size: 1.1rem;">Azure AD Groups Integration</h4>
                                            <p style="color: var(--text-muted); margin-bottom: 1rem; font-size: 0.9rem;">
                                                Automatically assign roles based on Azure AD group membership (coming soon).
                                            </p>

                                            <div class="settings-grid">
                                                <div class="form-group" style="grid-column: 1 / -1;">
                                                    <label class="checkbox-label">
                                                        <input type="checkbox" id="enableMicrosoftGroups" disabled>
                                                        <span>Enable Azure AD Groups Auto-Role Assignment</span>
                                                    </label>
                                                    <small>Feature in development - Auto-grant roles based on Azure AD group membership</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="microsoftAdminEmail">Microsoft Admin Email</label>
                                                    <input type="text" id="microsoftAdminEmail" placeholder="admin@<?php echo htmlspecialchars($primaryDomain); ?>" disabled>
                                                    <small>Admin email for Azure AD API access</small>
                                                </div>

                                                <div class="form-group" style="grid-column: 1 / -1;">
                                                    <label for="microsoftGroupRoleAssociations">Group-to-Role Associations</label>
                                                    <div style="display: flex; gap: 0.5rem; align-items: flex-start;">
                                                        <input type="text" id="microsoftGroupRoleAssociations" placeholder="group-id-1:student|group-id-2:staff" disabled style="flex: 1;">
                                                        <button type="button" class="btn-sm" onclick="openMicrosoftGroupModal()" disabled style="white-space: nowrap;">
                                                            Add Group
                                                        </button>
                                                    </div>
                                                    <small>Format: azure-group-id:role1,role2 | Use Azure AD Group IDs (not names) | Multiple roles: comma-separated | Multiple groups: pipe-separated | Or use "Add Group" button when enabled</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div style="margin-top: 1rem; padding: 1rem; background: #EFF6FF; border-left: 4px solid #3B82F6; border-radius: 4px; font-size: 0.875rem;">
                                            <strong>Setup Instructions:</strong>
                                            <ol style="margin: 0.5rem 0 0 1.5rem; padding: 0;">
                                                <li>Create app in <a href="https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank">Azure Portal</a></li>
                                                <li>Add redirect URI in "Authentication" section</li>
                                                <li>Create client secret in "Certificates & secrets"</li>
                                                <li>Grant "User.Read" API permission (Microsoft Graph)</li>
                                                <li>Copy Application ID and Client Secret here</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Database Configuration -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Database Configuration
                                        <span class="color-section-badge">4</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                            MySQL/MariaDB connection settings. <strong>Changes require application refresh to take effect.</strong>
                                        </p>
                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <label for="dbHost">Database Host</label>
                                                <input type="text" id="dbHost" placeholder="localhost">
                                                <small>MySQL server hostname or IP address</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="dbName">Database Name</label>
                                                <input type="text" id="dbName" placeholder="thehub">
                                                <small>Name of the database to connect to</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="dbUser">Database Username</label>
                                                <input type="text" id="dbUser" placeholder="db_user">
                                                <small>Database user with full permissions on the database</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="dbPassword">Database Password</label>
                                                <input type="password" id="dbPassword" placeholder="••••••••">
                                                <small>Password for database user (leave empty to keep current)</small>
                                            </div>
                                        </div>

                                        <div style="margin-top: 1rem; padding: 1rem; background: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 4px;">
                                            <strong>Database Connection Test:</strong>
                                            <button id="testDbConnection" class="btn btn-sm btn-secondary" style="margin-left: 1rem;">Test Connection</button>
                                            <div id="dbTestResult" style="margin-top: 0.5rem;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Application Settings -->
                            <div class="color-section">
                                <div class="color-section-header" id="appSettingsHeader" onclick="toggleColorSection(this)">
                                    <h3>
                                        Application Settings
                                        <span class="color-section-badge">5</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                            Core application configuration
                                        </p>
                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <label for="appUrl">Application URL</label>
                                                <input type="text" id="appUrl" placeholder="https://hub.example.com">
                                                <small>Full URL where The Hub is accessed (used for OAuth callbacks)</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="appEnvironment">Environment</label>
                                                <select id="appEnvironment">
                                                    <option value="production">Production</option>
                                                    <option value="development">Development</option>
                                                    <option value="staging">Staging</option>
                                                </select>
                                                <small>Current environment mode (affects debugging and error display)</small>
                                            </div>

                                            <div class="form-group">
                                                <label>
                                                    <input type="checkbox" id="debugMode" style="width: auto; margin-right: 0.5rem;">
                                                    Enable Debug Mode
                                                </label>
                                                <small>Show detailed error messages (WARNING: disable in production!)</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="maxUploadSize">Max Upload Size (MB)</label>
                                                <input type="number" id="maxUploadSize" min="1" max="100" value="10">
                                                <small>Maximum file size for logo and branding uploads (1-100 MB)</small>
                                            </div>

                                            <div class="form-group">
                                                <label>
                                                    <input type="checkbox" id="maintenanceMode" style="width: auto; margin-right: 0.5rem;">
                                                    Maintenance Mode
                                                </label>
                                                <small>WARNING: Temporarily disable access for non-admin users</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Email Configuration -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Email Configuration (SMTP)
                                        <span class="color-section-badge">6</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                            SMTP settings for sending system emails (invitation notifications, password resets, etc.)
                                        </p>
                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <label for="smtpHost">SMTP Host</label>
                                                <input type="text" id="smtpHost" placeholder="smtp.gmail.com">
                                                <small>SMTP server hostname</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="smtpPort">SMTP Port</label>
                                                <input type="number" id="smtpPort" placeholder="587">
                                                <small>Usually 587 (TLS) or 465 (SSL)</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="smtpUsername">SMTP Username</label>
                                                <input type="text" id="smtpUsername" placeholder="noreply@example.com">
                                                <small>Email account for sending</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="smtpPassword">SMTP Password</label>
                                                <input type="password" id="smtpPassword" placeholder="••••••••">
                                                <small>Password or app-specific password (leave empty to keep current)</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="smtpFromEmail">From Email Address</label>
                                                <input type="email" id="smtpFromEmail" placeholder="noreply@example.com">
                                                <small>Email address shown as sender</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="smtpFromName">From Name</label>
                                                <input type="text" id="smtpFromName" placeholder="The Hub">
                                                <small>Name shown as sender</small>
                                            </div>
                                        </div>

                                        <div style="margin-top: 1rem; padding: 1rem; background: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 4px;">
                                            <strong>Test SMTP:</strong>
                                            <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem; flex-wrap: wrap;">
                                                <input type="email" id="testEmailAddress" placeholder="your@email.com" style="flex: 1; min-width: 200px;">
                                                <button id="testSmtpConfig" class="btn btn-sm btn-secondary">Test Connection</button>
                                                <button id="sendTestEmail" class="btn btn-sm btn-primary">Send Test Email</button>
                                            </div>
                                            <div id="smtpTestResult" style="margin-top: 0.5rem;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!--
                            ============================================================
                            EXAMPLE: Email Notifications (Future Feature)
                            ============================================================
                            This is a template showing cascading dependencies pattern.
                            Uncomment and adapt when implementing email notifications.

                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Email Notifications
                                        <span class="color-section-badge">4+</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                            Configure automated email notifications for system events.
                                        </p>

                                        <div class="settings-grid">
                                            LEVEL 1: Top-level feature toggle
                                            <div class="form-group" style="grid-column: 1 / -1;">
                                                <label class="checkbox-label">
                                                    <input type="checkbox" id="enableEmailNotifications"
                                                           onchange="toggleDependentSection('enableEmailNotifications', 'emailNotificationFields', true)">
                                                    <span>Enable Email Notifications</span>
                                                </label>
                                                <small>Send automated emails for system events</small>
                                            </div>

                                            LEVEL 2: Dependent fields appear when Level 1 is checked
                                            <div id="emailNotificationFields" style="display: none; grid-column: 1 / -1;">
                                                <div class="settings-grid" style="padding-left: 2rem; border-left: 3px solid var(--primary-color);">

                                                    <div class="form-group">
                                                        <label class="checkbox-label">
                                                            <input type="checkbox" id="notifyOnNewUser">
                                                            <span>New User Registrations</span>
                                                        </label>
                                                        <small>Notify admins when new users register</small>
                                                    </div>

                                                    LEVEL 3: Email Digest depends on Email Notifications
                                                    <div class="form-group" style="grid-column: 1 / -1; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                                                        <label class="checkbox-label">
                                                            <input type="checkbox" id="enableEmailDigest"
                                                                   onchange="toggleDependentSection('enableEmailDigest', 'emailDigestFields', true)">
                                                            <span>Enable Daily Email Digest</span>
                                                        </label>
                                                        <small>Send summary emails instead of individual notifications</small>
                                                    </div>

                                                    LEVEL 4: Digest configuration depends on Digest being enabled
                                                    <div id="emailDigestFields" style="display: none; grid-column: 1 / -1;">
                                                        <div class="settings-grid" style="padding-left: 2rem; border-left: 3px solid var(--secondary-color);">
                                                            <div class="form-group">
                                                                <label for="digestTime">Send Time</label>
                                                                <input type="time" id="digestTime" value="08:00">
                                                                <small>Daily digest send time (server timezone)</small>
                                                            </div>

                                                            <div class="form-group">
                                                                <label class="checkbox-label">
                                                                    <input type="checkbox" id="digestIncludeUsers">
                                                                    <span>Include User Activity</span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div style="margin-top: 1rem; padding: 1rem; background: #EFF6FF; border-left: 4px solid #3B82F6; border-radius: 4px;">
                                            <strong>💡 Cascading Dependencies Example:</strong><br>
                                            This section demonstrates 4 levels of dependencies:<br>
                                            1. Enable Email Notifications (top-level)<br>
                                            &nbsp;&nbsp;→ 2. Event Types (checkboxes)<br>
                                            &nbsp;&nbsp;&nbsp;&nbsp;→ 3. Enable Email Digest<br>
                                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;→ 4. Digest Options<br>
                                            <br>
                                            Unchecking Level 1 cascades down and disables/hides all children.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            -->

                            <!-- Danger Zone -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)" style="border-color: #DC2626;">
                                    <h3 style="color: #DC2626;">
                                        Danger Zone
                                        <span class="color-section-badge" style="background: #DC2626;">3</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed" style="color: #DC2626;">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: #991B1B; margin-bottom: 1rem; font-weight: 600;">
                                            WARNING: These actions cannot be undone. Proceed with extreme caution.
                                        </p>
                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <button id="resetToDefaults" class="btn btn-danger">Reset All Color Settings to Defaults</button>
                                                <small>WARNING: This will restore all color/branding settings to their original values</small>
                                            </div>

                                            <div class="form-group">
                                                <button id="clearAllSessions" class="btn btn-danger">Clear All Active Sessions</button>
                                                <small>WARNING: Force logout all users (including yourself)</small>
                                            </div>

                                            <div class="form-group">
                                                <button id="regenerateEnv" class="btn btn-danger">Regenerate .env File</button>
                                                <small>WARNING: Re-create .env from current settings (backup created automatically)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Save Advanced Settings -->
                            <div class="advanced-settings-footer">
                                <div class="advanced-settings-footer-content">
                                    <span id="unsavedChangesIndicator" class="unsaved-indicator">Unsaved changes</span>
                                    <button id="saveAdvancedSettings" class="btn btn-primary">Save Configuration</button>
                                    <button id="reloadAdvancedSettings" class="btn btn-secondary">Reload</button>
                                </div>
                            </div>
                        </div><!-- end subtab-advanced -->
                    </div><!-- end site-settings-container -->
                </div><!-- end tab-content-scroll -->
            </div><!-- end tab-site-settings -->
