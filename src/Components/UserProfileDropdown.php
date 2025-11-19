<?php
/**
 * User Profile Dropdown Component
 * 
 * Standalone, reusable component for user profile display
 * Can be included in any page (Hub, Management, Admin)
 * 
 * Usage:
 *   require_once __DIR__ . '/../src/components/UserProfileDropdown.php';
 *   \Hub\Components\UserProfileDropdown::render($user, $userRole);
 * 
 * Required data:
 *   $user = ['id' => 1, 'name' => 'John Doe', 'picture' => 'url', 'email' => 'john@example.com']
 *   $userRole = 'admin' | 'teacher' | 'student' | etc.
 */

namespace Hub\Components;

class UserProfileDropdown
{
    /**
     * Render the user profile dropdown HTML
     * 
     * @param array $user User data (id, name, picture, email)
     * @param string $userRole User's effective role
     * @param array $options Optional customization
     *   - 'show_preferences' => bool (default: true)
     *   - 'show_contact' => bool (default: true)
     *   - 'profile_url' => string (default: '/profile.php')
     *   - 'logout_url' => string (default: '/logout.php')
     *   - 'custom_links' => array of ['url' => '', 'icon' => '', 'label' => '']
     */
    public static function render($user, $userRole, $options = [])
    {
        // Default options
        $defaults = [
            'show_preferences' => true,
            'show_contact' => true,
            'profile_url' => '/profile.php',
            'logout_url' => '/logout.php',
            'custom_links' => []
        ];
        $opts = array_merge($defaults, $options);

        // Safe helper for avatar URL
        $avatarUrl = self::safeAvatarUrl($user['picture'] ?? null);
        $userName = htmlspecialchars($user['name'] ?? 'User', ENT_QUOTES, 'UTF-8');
        $userRoleFormatted = ucfirst(str_replace('_', ' ', $userRole));
        
        ?>
        <!-- User Profile Dropdown Component -->
        <div class="nav-user-dropdown">
            <button class="nav-user-trigger"
                    id="userDropdownTrigger"
                    aria-expanded="false"
                    aria-haspopup="true"
                    aria-controls="userDropdownMenu">
                <img src="<?= $avatarUrl ?>"
                     alt="<?= $userName ?>"
                     class="nav-user-avatar">
                <div class="nav-user-info">
                    <div class="nav-user-name"><?= $userName ?></div>
                    <div class="nav-user-role"><?= $userRoleFormatted ?></div>
                </div>
                <span class="nav-user-arrow">▼</span>
            </button>

            <div class="nav-user-menu"
                 id="userDropdownMenu"
                 role="menu"
                 aria-labelledby="userDropdownTrigger">
                <a href="<?= htmlspecialchars($opts['profile_url'], ENT_QUOTES, 'UTF-8') ?>" 
                   class="user-menu-item" 
                   role="menuitem">
                    <span class="user-menu-icon"><i class="fas fa-user"></i></span>
                    <span>My Profile</span>
                </a>
                
                <?php if ($opts['show_contact']): ?>
                <a href="<?= htmlspecialchars($opts['profile_url'], ENT_QUOTES, 'UTF-8') ?>?tab=contact" 
                   class="user-menu-item">
                    <span class="user-menu-icon"><i class="fas fa-envelope"></i></span>
                    <span>Contact Preferences</span>
                </a>
                <?php endif; ?>
                
                <?php if ($opts['show_preferences']): ?>
                <a href="<?= htmlspecialchars($opts['profile_url'], ENT_QUOTES, 'UTF-8') ?>?tab=preferences" 
                   class="user-menu-item">
                    <span class="user-menu-icon"><i class="fas fa-cog"></i></span>
                    <span>Preferences</span>
                </a>
                <?php endif; ?>

                <?php if (!empty($opts['custom_links'])): ?>
                    <?php foreach ($opts['custom_links'] as $link): ?>
                    <a href="<?= htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') ?>" 
                       class="user-menu-item">
                        <span class="user-menu-icon"><i class="<?= htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                        <span><?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div class="user-menu-divider"></div>
                <a href="<?= htmlspecialchars($opts['logout_url'], ENT_QUOTES, 'UTF-8') ?>" 
                   class="user-menu-item user-menu-logout">
                    <span class="user-menu-icon"><i class="fas fa-sign-out-alt"></i></span>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <?php self::renderDropdownScript(); ?>
        <?php
    }

    /**
     * Get safe avatar URL with fallback
     * 
     * @param string|null $pictureUrl User's picture URL
     * @return string Safe avatar URL
     */
    private static function safeAvatarUrl($pictureUrl)
    {
        if (empty($pictureUrl)) {
            return '/assets/images/default-avatar.png';
        }

        // Allow data URIs, external URLs (https), and local paths
        if (
            strpos($pictureUrl, 'data:image/') === 0 ||
            strpos($pictureUrl, 'https://') === 0 ||
            strpos($pictureUrl, '/') === 0
        ) {
            return htmlspecialchars($pictureUrl, ENT_QUOTES, 'UTF-8');
        }

        // Invalid URL format - use default
        return '/assets/images/default-avatar.png';
    }

    /**
     * Render the dropdown toggle JavaScript
     * Only renders once per page load
     */
    private static function renderDropdownScript()
    {
        static $scriptRendered = false;
        if ($scriptRendered) {
            return;
        }
        $scriptRendered = true;
        ?>
        <script>
        (function() {
            // User Profile Dropdown Toggle
            const trigger = document.getElementById('userDropdownTrigger');
            const menu = document.getElementById('userDropdownMenu');
            
            if (!trigger || !menu) return;

            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                const isExpanded = trigger.getAttribute('aria-expanded') === 'true';
                trigger.setAttribute('aria-expanded', !isExpanded);
                menu.classList.toggle('active');
            });

            // Close on outside click
            document.addEventListener('click', function(e) {
                if (!trigger.contains(e.target) && !menu.contains(e.target)) {
                    trigger.setAttribute('aria-expanded', 'false');
                    menu.classList.remove('active');
                }
            });

            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && menu.classList.contains('active')) {
                    trigger.setAttribute('aria-expanded', 'false');
                    menu.classList.remove('active');
                    trigger.focus();
                }
            });
        })();
        </script>
        <?php
    }
}
