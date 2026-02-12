<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Hub\Package\PolicyEngine;
use Hub\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Package Rate Limit Middleware
 *
 * Enforces per-user, per-action rate limiting for package operations.
 * Uses PolicyEngine's rate limiting (DB-backed with sliding window).
 *
 * Usage in routes:
 *   ->middleware('package.rate:mutation')     // mutation limits
 *   ->middleware('package.rate:query')        // query limits
 *   ->middleware('package.rate:reveal')       // sensitive reveal limits
 *   ->middleware('package.rate:export')       // export limits
 *   ->middleware('package.rate:custom,10,60') // 10 requests per 60 seconds
 *
 * @see PolicyEngine::isRateLimited()
 * @see PolicyEngine::recordRateHit()
 */
class PackageRateLimit
{
    private PolicyEngine $policy;

    public function __construct()
    {
        $this->policy = new PolicyEngine();
    }

    /**
     * Handle an incoming request
     *
     * @param Request $request
     * @param Closure $next
     * @param string $type Action type: query, mutation, reveal, export, or 'custom,max,window'
     */
    public function handle(Request $request, Closure $next, string $type = 'query'): Response
    {
        // Get current user
        $userId = $request->session()->get('user_id') ?? $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $next($request); // No user = no rate limiting
        }

        // Parse custom limits from middleware parameter
        $limits = $this->parseLimits($type);
        $actionType = $limits['type'];

        // Build action key from route
        $packageId = $request->route('packageId') ?? $request->input('package_id') ?? 'unknown';
        $actionName = $request->route('action') ?? $request->input('action') ?? $request->path();
        $actionKey = "{$packageId}.{$actionType}.{$actionName}";

        // Create minimal user object for policy check
        $user = $this->resolveUser($userId);
        if (!$user) {
            return $next($request);
        }

        // Check rate limit
        if ($this->policy->isRateLimited($user, $actionKey, $limits)) {
            $status = $this->policy->getRateLimitStatus($user, $actionKey, $limits);

            // Log the rate limit hit
            error_log("[RATE_LIMIT] User {$userId} exceeded {$actionType} limit on {$actionKey}");

            // Return 429 Too Many Requests
            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded. Please try again later.',
                'retryAfter' => $status['resetAt'],
                'limit' => $status['limit'],
                'remaining' => 0,
            ], 429, [
                'Retry-After' => max(1, strtotime($status['resetAt']) - time()),
                'X-RateLimit-Limit' => $status['limit'],
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => strtotime($status['resetAt']),
            ]);
        }

        // Process request
        $response = $next($request);

        // Record the hit after successful processing
        $this->policy->recordRateHit($user, $actionKey, $limits);

        // Add rate limit headers to response
        $status = $this->policy->getRateLimitStatus($user, $actionKey, $limits);
        if ($response instanceof \Illuminate\Http\JsonResponse || method_exists($response, 'header')) {
            $response->header('X-RateLimit-Limit', $status['limit']);
            $response->header('X-RateLimit-Remaining', $status['remaining']);
            $response->header('X-RateLimit-Reset', strtotime($status['resetAt']));
        }

        return $response;
    }

    /**
     * Parse limit parameters from middleware string
     *
     * 'mutation' → ['type' => 'mutation']
     * 'custom,10,60' → ['type' => 'custom', 'max' => 10, 'window' => 60]
     */
    private function parseLimits(string $type): array
    {
        $parts = explode(',', $type);
        $limits = ['type' => $parts[0]];

        if (count($parts) >= 3) {
            $limits['max'] = (int)$parts[1];
            $limits['window'] = (int)$parts[2];
        }

        return $limits;
    }

    /**
     * Resolve user object from ID
     */
    private function resolveUser(int $userId): ?User
    {
        try {
            return User::find($userId);
        } catch (\Exception $e) {
            return null;
        }
    }
}
