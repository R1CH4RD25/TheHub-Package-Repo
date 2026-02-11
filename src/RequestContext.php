<?php

namespace Hub;

/**
 * Request Context
 * 
 * Manages request-scoped data including correlation IDs for audit tracing.
 * Initialized once per HTTP request in bootstrap.php.
 * 
 * Security: Uses UUID v4 instead of uniqid() for better uniqueness under
 * concurrency and distributed systems.
 */
class RequestContext
{
    private static ?string $correlationId = null;
    private static ?string $ipAddress = null;
    private static ?string $userAgent = null;

    /**
     * Initialize request context (call once per request in bootstrap)
     */
    public static function init(): void
    {
        if (self::$correlationId === null) {
            self::$correlationId = self::generateUuidV4();
            self::$ipAddress = self::captureRealIp();
            self::$userAgent = self::captureUserAgent();
        }
    }

    /**
     * Get correlation ID for current request
     * 
     * @return string UUID v4 correlation ID
     */
    public static function getCorrelationId(): string
    {
        if (self::$correlationId === null) {
            self::init();
        }

        return self::$correlationId;
    }

    /**
     * Get real client IP address (proxy-aware)
     * 
     * @return string|null Client IP address
     */
    public static function getIpAddress(): ?string
    {
        if (self::$ipAddress === null) {
            self::init();
        }

        return self::$ipAddress;
    }

    /**
     * Get user agent
     * 
     * @return string|null User agent string (truncated to 255 chars)
     */
    public static function getUserAgent(): ?string
    {
        if (self::$userAgent === null) {
            self::init();
        }

        return self::$userAgent;
    }

    /**
     * Generate UUID v4 (RFC 4122 compliant)
     * 
     * Better than uniqid() for:
     * - Distributed systems (no hostname collision)
     * - High concurrency (cryptographically random)
     * - Standard format (parseable by monitoring tools)
     * 
     * @return string UUID v4 (e.g., "550e8400-e29b-41d4-a716-446655440000")
     */
    private static function generateUuidV4(): string
    {
        $data = random_bytes(16);

        // Set version (4) and variant (10xx)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant 10xx

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Capture real client IP address (proxy-aware)
     * 
     * Checks trusted proxy headers in order:
     * 1. CF-Connecting-IP (Cloudflare)
     * 2. X-Forwarded-For (standard proxy header)
     * 3. X-Real-IP (Nginx)
     * 4. REMOTE_ADDR (direct connection)
     * 
     * Security: Only use proxy headers if you trust your reverse proxy.
     * Configure trusted proxies in .env (future enhancement).
     * 
     * @return string|null Valid IP address
     */
    private static function captureRealIp(): ?string
    {
        // TODO: Add trusted proxy configuration in .env
        // For now, trust common proxy headers (secure for internal networks)
        
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Standard proxy
            'HTTP_X_REAL_IP',        // Nginx
            'REMOTE_ADDR'            // Direct connection
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated list (take first IP)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }

                // Validate IP format
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * Capture user agent string
     * 
     * @return string|null User agent (truncated to 255 chars)
     */
    private static function captureUserAgent(): ?string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        if ($userAgent && strlen($userAgent) > 255) {
            $userAgent = substr($userAgent, 0, 255);
        }

        return $userAgent;
    }

    /**
     * Reset context (for testing)
     */
    public static function reset(): void
    {
        self::$correlationId = null;
        self::$ipAddress = null;
        self::$userAgent = null;
    }
}
