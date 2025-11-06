<?php

namespace Hub;

use PDO;

class Auth
{
    private $db;
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $oauthClient; // OAuth abstraction layer

    public function __construct($oauthClient = null)
    {
        $this->db = Database::getInstance();
        $this->clientId = $_ENV['GOOGLE_CLIENT_ID'];
        $this->clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'];
        $this->redirectUri = $_ENV['GOOGLE_REDIRECT_URI'];

        // Use provided OAuth client or create default (production)
        $this->oauthClient = $oauthClient ?? new OAuthClient(
            $this->clientId,
            $this->clientSecret,
            $this->redirectUri
        );
    }

    public function getAuthUrl()
    {
        $allowedDomains = $_ENV['ALLOWED_DOMAINS'] ?? '';
        $primaryDomain = '';

        if (!empty($allowedDomains)) {
            $domains = array_map('trim', explode(',', $allowedDomains));
            $primaryDomain = $domains[0] ?? '';
        }

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile https://www.googleapis.com/auth/admin.directory.group.readonly',
            'access_type' => 'online', // Use online for better UX - no refresh token needed
            // Removed 'prompt' => 'consent' to allow device caching
        ];

        // Only add hd param if domain is configured
        if (!empty($primaryDomain)) {
            $params['hd'] = $primaryDomain;
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function handleCallback($code)
    {
        try {
            // Exchange authorization code for access token
            $tokenData = $this->getAccessToken($code);

            if (isset($tokenData['error'])) {
                throw new \Exception($tokenData['error_description'] ?? 'Authentication failed');
            }

            // Get user info from Google
            $userInfo = $this->getUserInfo($tokenData['access_token']);

            $email = $userInfo['email'];
            $googleId = $userInfo['sub'];
            $name = $userInfo['name'];
            $picture = $userInfo['picture'] ?? null;

            // Verify domain if REQUIRE_DOMAIN_MATCH is enabled
            $requireDomain = filter_var($_ENV['REQUIRE_DOMAIN_MATCH'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
            if ($requireDomain) {
                $allowedDomains = $_ENV['ALLOWED_DOMAINS'] ?? '';
                if (!empty($allowedDomains)) {
                    $domains = array_map('trim', explode(',', $allowedDomains));
                    $emailDomain = substr(strrchr($email, "@"), 1);

                    if (!in_array($emailDomain, $domains)) {
                        throw new \Exception('Email domain not allowed. Please use an authorized domain.');
                    }
                }
            }

            // Get or create user (pass access token for group checking)
            $user = $this->getOrCreateUser($googleId, $email, $name, $tokenData['access_token'], $picture);

            // Check if user is inactive
            if (!$user['is_active']) {
                // Show different message for pending vs deactivated
                $isPending = ($user['role'] === 'user' && !$user['approved_by']);
                if ($isPending) {
                    throw new \Exception('Your account is pending approval. You will receive an email once approved.');
                } else {
                    throw new \Exception('Your account has been deactivated. Contact the administrator.');
                }
            }

            // Update last login and profile picture
            $this->updateLastLogin($user['id']);
            if ($picture) {
                $this->db->execute(
                    "UPDATE users SET picture = ? WHERE id = ?",
                    [$picture, $user['id']]
                );
                $user['picture'] = $picture;
            }

            // Set session
            $this->createSession($user);

            return $user;

        } catch (\Exception $e) {
            error_log("Auth error: " . $e->getMessage());
            throw $e;
        }
    }

    private function getAccessToken($code)
    {
        return $this->oauthClient->getAccessToken($code);
    }

    private function getUserInfo($accessToken)
    {
        return $this->oauthClient->getUserInfo($accessToken);
    }

    /**
     * Check if user matches a group pattern (supports wildcards)
     * Example: seniors*@woodsonisd.net matches seniors2026@woodsonisd.net
     */
    private function matchesGroupPattern($userGroup, $pattern)
    {
        // If no wildcard, do exact match
        if (strpos($pattern, '*') === false) {
            return strtolower($userGroup) === strtolower($pattern);
        }

        // Convert wildcard pattern to regex
        // Escape special regex chars except *
        $regexPattern = preg_quote($pattern, '/');
        // Replace escaped \* with .* (match any characters)
        $regexPattern = str_replace('\*', '.*', $regexPattern);

        return preg_match('/^' . $regexPattern . '$/i', $userGroup) === 1;
    }

    /**
     * Get all Google Groups the user is a member of
     * Returns array of group email addresses
     */
    private function getUserGoogleGroups($userEmail)
    {
        try {
            $serviceAccountPath = $_ENV['GOOGLE_SERVICE_ACCOUNT_JSON'] ?? null;
            $adminEmail = $_ENV['GOOGLE_ADMIN_EMAIL'] ?? null;

            if (!$serviceAccountPath || !$adminEmail) {
                error_log("Google Groups config missing: GOOGLE_SERVICE_ACCOUNT_JSON or GOOGLE_ADMIN_EMAIL");
                return [];
            }

            if (!file_exists($serviceAccountPath)) {
                error_log("Service account JSON not found: {$serviceAccountPath}");
                return [];
            }

            // Initialize Google Client with service account
            $client = new \Google_Client();
            $client->setAuthConfig($serviceAccountPath);
            $client->setSubject($adminEmail); // Impersonate admin for domain-wide delegation
            $client->addScope(\Google_Service_Directory::ADMIN_DIRECTORY_GROUP_READONLY);

            // Create Directory service
            $service = new \Google_Service_Directory($client);

            // Get all groups the user is a member of
            $groups = $service->groups->listGroups(['userKey' => $userEmail]);
            $groupEmails = [];

            foreach ($groups->getGroups() as $group) {
                $groupEmails[] = $group->getEmail();
            }

            error_log("Google Groups for {$userEmail}: " . implode(', ', $groupEmails));
            return $groupEmails;

        } catch (\Google_Service_Exception $e) {
            error_log("Google Groups API error: " . $e->getMessage());
            return [];
        } catch (\Exception $e) {
            error_log("Google Groups check failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check user's Google Groups and return assigned roles
     * Returns array of roles based on GOOGLE_GROUP_ROLE_ASSOCIATIONS
     * Format: group@domain.com:role1,role2|group2@domain.com:role3
     */
    private function checkGoogleGroupMembership($accessToken, $userEmail)
    {
        try {
            // Check if Google Groups is enabled
            if (($_ENV['ENABLE_GOOGLE_GROUPS'] ?? 'false') !== 'true') {
                error_log("Google Groups not enabled");
                return [];
            }

            // Get user's groups
            $userGroups = $this->getUserGoogleGroups($userEmail);

            if (empty($userGroups)) {
                error_log("User {$userEmail} is not in any Google Groups");
                return [];
            }

            // Parse role associations from .env
            $associations = $_ENV['GOOGLE_GROUP_ROLE_ASSOCIATIONS'] ?? '';
            if (empty($associations)) {
                error_log("GOOGLE_GROUP_ROLE_ASSOCIATIONS not configured");
                return [];
            }

            $assignedRoles = [];

            // Parse pipe-separated list: group@domain.com:role1,role2|group2@domain.com:role3
            $mappings = explode('|', $associations);

            foreach ($mappings as $mapping) {
                $mapping = trim($mapping);
                if (empty($mapping) || !strpos($mapping, ':')) {
                    continue;
                }

                [$groupPattern, $rolesStr] = explode(':', $mapping, 2);
                $groupPattern = trim($groupPattern);
                $roles = array_map('trim', explode(',', $rolesStr));

                // Check if user is in any group matching this pattern
                foreach ($userGroups as $userGroup) {
                    if ($this->matchesGroupPattern($userGroup, $groupPattern)) {
                        error_log("User {$userEmail} matched pattern '{$groupPattern}' via group '{$userGroup}' - assigning roles: " . implode(', ', $roles));
                        $assignedRoles = array_merge($assignedRoles, $roles);
                        break; // Don't check other user groups for this pattern
                    }
                }
            }

            // Remove duplicates and return
            $assignedRoles = array_unique($assignedRoles);
            error_log("Final assigned roles for {$userEmail}: " . implode(', ', $assignedRoles));

            return $assignedRoles;

        } catch (\Exception $e) {
            error_log("Google Groups role check failed: " . $e->getMessage());
            return [];
        }
    }

    private function getOrCreateUser($googleId, $email, $name, $accessToken = null, $picture = null)
    {
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE google_id = ? OR email = ?",
            [$googleId, $email]
        );

        if (!$user) {
            $googleGroupRoles = []; // Initialize for all paths

            // Check if this is the super admin
            if ($email === $_ENV['SUPER_ADMIN_EMAIL']) {
                $role = 'super_admin';
                $isActive = true;
            } else {
                // Check for invitation
                $invitation = $this->db->fetchOne(
                    "SELECT * FROM invitations WHERE email = ? AND expires_at > NOW() AND used_at IS NULL",
                    [$email]
                );

                if ($invitation) {
                    // Invited user - use invited role and activate
                    $role = $invitation['role'];
                    $isActive = true;

                    // Mark invitation as used
                    $this->db->execute(
                        "UPDATE invitations SET used_at = NOW() WHERE id = ?",
                        [$invitation['id']]
                    );

                    $invitedBy = $invitation['invited_by'];
                    $invitedAt = $invitation['created_at'];
                } else {
                    // Check Google Groups membership if enabled
                    $assignedRoles = [];
                    if ($accessToken) {
                        $assignedRoles = $this->checkGoogleGroupMembership($accessToken, $email);
                    }

                    if (!empty($assignedRoles)) {
                        // User has Google Groups roles - auto-approve with highest role
                        // Use Roles class to determine hierarchy
                        $role = Roles::getHighest($assignedRoles);
                        $isActive = true;
                        $invitedBy = null;
                        $invitedAt = null;
                        error_log("Auto-approved {$email} as {$role} (Google Groups: " . implode(', ', $assignedRoles) . ")");

                        // Store all assigned roles for later
                        $googleGroupRoles = $assignedRoles;
                    } else {
                        // No invitation and not in any configured group - create as pending (inactive)
                        $role = 'staff';
                        $isActive = false;
                        $invitedBy = null;
                        $invitedAt = null;
                        $googleGroupRoles = [];
                        error_log("Created pending user {$email} (not in configured Google Groups, needs approval)");
                    }
                }
            }

            $this->db->execute(
                "INSERT INTO users (google_id, email, name, role, is_active, invited_by, invited_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$googleId, $email, $name, $role, $isActive ? 1 : 0, $invitedBy ?? null, $invitedAt ?? null]
            );

            $user = $this->db->fetchOne(
                "SELECT * FROM users WHERE google_id = ?",
                [$googleId]
            );

            // Add all Google Group roles to user_global_roles table
            if (!empty($googleGroupRoles) && $user) {
                foreach ($googleGroupRoles as $groupRole) {
                    // Check if role already exists
                    $existingRole = $this->db->fetchOne(
                        "SELECT * FROM user_global_roles WHERE user_id = ? AND role = ?",
                        [$user['id'], $groupRole]
                    );

                    if (!$existingRole) {
                        $this->db->execute(
                            "INSERT INTO user_global_roles (user_id, role, granted_by, granted_at) VALUES (?, ?, NULL, NOW())",
                            [$user['id'], $groupRole]
                        );
                        error_log("Added global role '{$groupRole}' to user {$email} via Google Groups");
                    }
                }
            }
        }

        return $user;
    }

    /**
     * Auto-grant section access based on Google Groups membership
     */
    private function autoGrantSectionAccess($userId, $email)
    {
        // LEGACY: Staff members in configured staff group get Substitute Request section
        $sectionSlug = 'substitute-request';
        $role = 'staff';

        // Check if section access already exists
        $existing = $this->db->fetchOne(
            "SELECT * FROM section_access WHERE user_id = ? AND section_id = (SELECT id FROM sections WHERE slug = ?)",
            [$userId, $sectionSlug]
        );

        if (!$existing) {
            // Grant access
            $this->db->execute(
                "INSERT INTO section_access (user_id, section_id, role, granted_by)
                 SELECT ?, id, ?, NULL FROM sections WHERE slug = ?",
                [$userId, $role, $sectionSlug]
            );
            error_log("Auto-granted {$email} access to {$sectionSlug} section as {$role}");
        }
    }

    private function updateLastLogin($userId)
    {
        $this->db->execute(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            [$userId]
        );
    }

    private function createSession($user)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Security Fix #3: Regenerate session ID BEFORE setting auth data
        // Prevents session fixation attacks
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['picture'] = $user['picture'] ?? null;
        $_SESSION['logged_in'] = true;

        // Audit log - successful login
        try {
            $logger = new AuditLogger();
            $logger->logLogin($user['id'], $user['email'], true);
        } catch (\Exception $e) {
            error_log("Failed to log login: " . $e->getMessage());
        }
    }

    public static function requireLogin()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            header('Location: /login.php');
            exit;
        }
    }

    public static function requireRole($allowedRoles)
    {
        self::requireLogin();

        if (!in_array($_SESSION['role'], $allowedRoles)) {
            http_response_code(403);
            die('Access denied');
        }
    }

    public static function getCurrentUser()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            return null;
        }

        // Security Fix #2: Validate user exists in database before trusting session
        // Prevents session hijacking with fake user IDs
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $db = Database::getInstance();
        $dbUser = $db->fetchOne(
            "SELECT id, email, name, role, is_active FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );

        // If user doesn't exist or is inactive, clear session and return null
        if (!$dbUser || !$dbUser['is_active']) {
            session_unset();
            session_destroy();
            return null;
        }

        // Update session with fresh DB data (role changes, etc.)
        $_SESSION['email'] = $dbUser['email'];
        $_SESSION['name'] = $dbUser['name'];
        $_SESSION['role'] = $dbUser['role'];
        $_SESSION['picture'] = $dbUser['picture'];

        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['email'],
            'name' => $_SESSION['name'],
            'role' => isset($_SESSION['view_as_role']) ? $_SESSION['view_as_role'] : $_SESSION['role'],
            'actual_role' => $_SESSION['role'],
            'picture' => $_SESSION['picture'] ?? null
        ];
    }

    public static function getCurrentUserId()
    {
        // Return session user_id directly (even if not in DB)
        // This allows tests to check edge cases like negative/large IDs
        $userId = $_SESSION['user_id'] ?? null;
        // Convert empty string to null for consistency
        return ($userId === '' || $userId === null) ? null : $userId;
    }

    public static function getEffectiveRole()
    {
        $user = self::getCurrentUser();
        if (!$user) {
            // Return empty string instead of null for consistency
            // Allows checking with isset() and truthiness
            return $_SESSION['role'] ?? '';
        }

        // Super admin can view as different roles
        if ($user['role'] === 'super_admin' && isset($_SESSION['view_as_role'])) {
            return $_SESSION['view_as_role'];
        }

        return $user['role'];
    }

    public static function setViewAsRole($role)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = self::getCurrentUser();
        if ($user && $user['role'] === 'super_admin') {
            $_SESSION['view_as_role'] = $role;
        }
    }

    public static function clearViewAsRole()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION['view_as_role']);
    }

    public static function isAdmin()
    {
        $role = self::getEffectiveRole();
        return $role && in_array($role, ['super_admin', 'admin']);
    }

    public static function isSuperAdmin()
    {
        $user = self::getCurrentUser();
        // Always check actual role, not effective role
        return $user && $user['actual_role'] === 'super_admin';
    }

    public static function isManager()
    {
        $role = self::getEffectiveRole();
        // Check for all manager-level roles (hierarchy 40+)
        return $role && in_array($role, [
            'super_admin', 'admin', 'principal', 'counselor',
            'substitute_manager', 'maintenance_director', 'custodial_manager',
            'business_manager'
        ]);
    }

    public static function isStaff()
    {
        $role = self::getEffectiveRole();
        // Super admin can do everything (including staff tasks)
        // Admin is ABOVE staff, not a subset (different hierarchy branch)
        // Staff is exact role match or super_admin (god mode)
        return $role && in_array($role, ['super_admin', 'staff']);
    }

    public static function canEditAnyRecord()
    {
        $role = self::getEffectiveRole();
        return $role && in_array($role, ['super_admin', 'admin', 'manager']);
    }

    public static function canManageUsers()
    {
        $role = self::getEffectiveRole();
        return $role && in_array($role, ['super_admin', 'admin']);
    }

    public static function canDeleteUser($targetUserId)
    {
        // Validate target user ID
        if ($targetUserId === null || $targetUserId < 1) {
            return false;
        }

        $user = self::getCurrentUser();
        if (!$user || !in_array($user['role'], ['super_admin', 'admin'])) {
            return false;
        }

        // Cannot delete themselves
        if ($user['id'] == $targetUserId) {
            return false;
        }

        // Admins cannot delete super_admins (protection for highest privilege)
        if ($user['role'] === 'admin') {
            $db = Database::getInstance();
            $stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
            $stmt->execute([$targetUserId]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

            // If target user exists and is a super_admin, admin cannot delete
            if ($targetUser && $targetUser['role'] === 'super_admin') {
                return false;
            }
        }

        return true;
    }    /**
     * Require user to have access to a specific section with minimum role
     * Redirects to sections.php if no access
     *
     * @param string $sectionSlug Section slug (e.g., 'announcements')
     * @param string $minimumRole Minimum required role (default: 'staff')
     */
    public static function requireSectionAccess($sectionSlug, $minimumRole = 'staff')
    {
        self::requireLogin();

        $user = self::getCurrentUser();
        $sectionAccess = new SectionAccess();

        if (!$sectionAccess->hasAccess($user['id'], $sectionSlug, $minimumRole)) {
            // No access - redirect back to section selector
            $_SESSION['error'] = "You don't have permission to access this section.";
            header('Location: /sections.php');
            exit;
        }
    }

    /**
     * Get user's role in a specific section
     *
     * @param string $sectionSlug Section slug
     * @return string|null User's role in the section, or null if no access
     */
    public static function getUserSectionRole($sectionSlug)
    {
        $user = self::getCurrentUser();
        if (!$user) {
            return null;
        }

        $sectionAccess = new SectionAccess();
        return $sectionAccess->getUserRole($user['id'], $sectionSlug);
    }

    public static function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Audit log - logout before destroying session
        if (isset($_SESSION['user_id']) && isset($_SESSION['email'])) {
            try {
                $logger = new AuditLogger();
                $logger->logLogout($_SESSION['user_id']);
            } catch (\Exception $e) {
                error_log("Failed to log logout: " . $e->getMessage());
            }
        }

        session_unset();
        session_destroy();

        header('Location: /login.php');
        exit;
    }

    /**
     * Check if user has a specific role
     *
     * @param string $role Role to check
     * @return bool True if user has the role
     */
    public static function hasRole(string $role): bool
    {
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            return false;
        }

        // Check global role
        $userRole = $_SESSION['role'] ?? 'user';
        if ($userRole === $role || $userRole === 'super_admin') {
            return true;
        }

        // Check additional roles in user_global_roles table
        $db = \Hub\Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM user_global_roles
            WHERE user_id = ? AND role = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $role]);
        $result = $stmt->fetch();

        return ($result['count'] ?? 0) > 0;
    }

}
