<?php

namespace Hub;

/**
 * Security Helper Functions
 * Sanitization and validation helpers for user-controlled data
 */
class Helpers
{
    /**
     * Safely sanitize avatar URLs to prevent XSS via javascript: protocol
     *
     * @param string|null $url User-provided avatar URL
     * @return string Safe URL or default avatar path
     */
    public static function safeAvatarUrl($url)
    {
        if (empty($url)) {
            return '/assets/images/default-avatar.svg';
        }

        // Only allow http/https/data URIs and relative paths
        // Block javascript:, data:text, and other dangerous protocols
        if (preg_match('#^(https?://|/|data:image/)#i', $url)) {
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        }

        // Invalid protocol - return safe default
        return '/assets/images/default-avatar.svg';
    }

    /**
     * Safely sanitize icon class names to prevent attribute injection
     *
     * @param string $iconClass Icon class name (e.g., 'bi-kanban', 'fa-home')
     * @return string Safe icon class or default
     */
    public static function safeIconClass($iconClass)
    {
        if (empty($iconClass)) {
            return 'bi-kanban'; // Safe default
        }

        // Whitelist known icon library prefixes and valid CSS class characters
        // Allows: bi-*, fa-*, fas-*, far-*, fal-*, fab-* followed by alphanumeric/hyphen
        if (preg_match('/^(bi|fa|fas|far|fal|fab)-[\w-]+$/', $iconClass)) {
            return htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8');
        }

        // Invalid format - return safe default
        return 'bi-kanban';
    }

    /**
     * Validate and sanitize a URL for safe href/src attributes
     *
     * @param string $url URL to validate
     * @param bool $allowRelative Allow relative URLs (default: true)
     * @return string|null Sanitized URL or null if invalid
     */
    public static function safeUrl($url, $allowRelative = true)
    {
        if (empty($url)) {
            return null;
        }

        // Check for dangerous protocols
        if (preg_match('#^(javascript|data|vbscript):#i', $url)) {
            return null;
        }

        // Allow http/https
        if (preg_match('#^https?://#i', $url)) {
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        }

        // Allow relative paths if enabled
        if ($allowRelative && preg_match('#^/#', $url)) {
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        }

        // Allow anchor links
        if (preg_match('#^#', $url)) {
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        }

        return null;
    }

    /**
     * Generate a Content Security Policy nonce for inline scripts
     *
     * @return string Base64-encoded nonce
     */
    public static function getCspNonce()
    {
        if (!isset($_SESSION['csp_nonce'])) {
            $_SESSION['csp_nonce'] = base64_encode(random_bytes(16));
        }
        return $_SESSION['csp_nonce'];
    }
}
