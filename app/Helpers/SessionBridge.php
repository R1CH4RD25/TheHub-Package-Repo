<?php

namespace App\Helpers;

/**
 * Session Bridge - Allows legacy PHP code to use Laravel sessions
 * 
 * This bridges the gap between old $_SESSION code and Laravel's session system.
 * Use this in legacy PHP files (hub.php, modules.php, etc.)
 */
class SessionBridge
{
    /**
     * Start Laravel session for legacy PHP files
     */
    public static function start(): void
    {
        if (!app()->bound('session.store')) {
            // Bootstrap Laravel session
            $config = config('session');
            
            // Use file driver to match PHP sessions location
            $handler = new \Illuminate\Session\FileSessionHandler(
                app('files'),
                storage_path('framework/sessions'),
                $config['lifetime']
            );
            
            $session = new \Illuminate\Session\Store(
                $config['cookie'],
                $handler,
                null
            );
            
            $session->setId(request()->cookies->get($config['cookie']));
            $session->start();
            
            app()->instance('session.store', $session);
        }
    }
    
    /**
     * Get user from Laravel session
     */
    public static function getUser(): ?array
    {
        self::start();
        return session('user');
    }
    
    /**
     * Set user in Laravel session
     */
    public static function setUser(array $user): void
    {
        self::start();
        session(['user' => $user]);
        session()->save();
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn(): bool
    {
        return self::getUser() !== null;
    }
    
    /**
     * Destroy session
     */
    public static function destroy(): void
    {
        self::start();
        session()->flush();
        session()->regenerate();
    }
}
