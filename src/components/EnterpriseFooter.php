<?php
/**
 * Enterprise Footer Component
 * 
 * Shared footer for Management Console and Admin Dashboard
 * Similar to Hub footer but adapted for enterprise contexts
 * 
 * Usage:
 *   \Hub\Components\EnterpriseFooter::render($user, [
 *       'context' => 'management' | 'admin',
 *       'show_version' => true,
 *       'show_user' => false
 *   ]);
 */

namespace Hub\Components;

use Hub\SiteSettings;

class EnterpriseFooter
{
    /**
     * Render the enterprise footer
     * 
     * @param array $user User data
     * @param array $options Configuration options
     *   - 'context' => 'management' | 'admin'
     *   - 'show_version' => bool (default: true)
     *   - 'show_user' => bool (default: false)
     *   - 'show_custom_text' => bool (default: true)
     */
    public static function render($user, $options = [])
    {
        $defaults = [
            'context' => 'management',
            'show_version' => true,
            'show_user' => false,
            'show_custom_text' => true
        ];
        $opts = array_merge($defaults, $options);
        
        $orgName = SiteSettings::get('organization_name', 'Your Organization');
        $siteName = SiteSettings::get('site_name', 'The Hub');
        $contextLabel = $opts['context'] === 'admin' ? 'Admin Dashboard' : 'Management Console';
        $customText = SiteSettings::get('footer_custom_text');
        $version = SiteSettings::get('site_version', '1.0');
        
        ?>
        <!-- Enterprise Footer Component -->
        <footer class="enterprise-footer">
            <div class="footer-content">
                <div class="footer-left">
                    <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($orgName, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="footer-separator">•</span>
                    <span><?= htmlspecialchars($contextLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($opts['show_custom_text'] && !empty($customText)): ?>
                        <span class="footer-separator">•</span>
                        <span><?= htmlspecialchars($customText, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
                <div class="footer-right">
                    <?php if ($opts['show_version']): ?>
                        <span>Version <?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($opts['show_user']): ?>
                            <span class="footer-separator">•</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($opts['show_user']): ?>
                        <span>Logged in as <strong><?= htmlspecialchars($user['name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></strong></span>
                    <?php endif; ?>
                </div>
            </div>
        </footer>
        <?php
    }
}
