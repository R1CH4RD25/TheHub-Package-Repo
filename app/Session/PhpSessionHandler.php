<?php

namespace App\Session;

use SessionHandlerInterface;

/**
 * Custom session handler that reads PHP-serialized sessions
 * This allows Laravel to read sessions created by legacy PHP code
 */
class PhpSessionHandler implements SessionHandlerInterface
{
    private $savePath;
    private $sessionName;

    public function open($savePath, $sessionName): bool
    {
        $this->savePath = $savePath;
        $this->sessionName = $sessionName;
        
        if (!is_dir($savePath)) {
            mkdir($savePath, 0777, true);
        }
        
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read($sessionId): string|false
    {
        $file = "$this->savePath/sess_$sessionId";
        
        if (!file_exists($file)) {
            return '';
        }
        
        $data = file_get_contents($file);
        
        // If data is PHP-serialized (legacy format), convert to Laravel format
        if (strpos($data, '|') !== false && strpos($data, 'user_id|') !== false) {
            // Parse PHP session format: user_id|i:18384;email|s:31:"..."
            $laravelData = $this->convertPhpToLaravel($data);
            return $laravelData;
        }
        
        return $data;
    }

    public function write($sessionId, $data): bool
    {
        $file = "$this->savePath/sess_$sessionId";
        return file_put_contents($file, $data) !== false;
    }

    public function destroy($sessionId): bool
    {
        $file = "$this->savePath/sess_$sessionId";
        if (file_exists($file)) {
            unlink($file);
        }
        return true;
    }

    public function gc($maxlifetime): int|false
    {
        $files = glob("$this->savePath/sess_*");
        $deleted = 0;
        
        foreach ($files as $file) {
            if (filemtime($file) + $maxlifetime < time()) {
                unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }

    /**
     * Convert PHP session format to Laravel session format
     */
    private function convertPhpToLaravel($phpData): string
    {
        // Parse PHP session data
        $sessionData = [];
        session_decode($phpData);
        $sessionData = $_SESSION;
        
        // Build Laravel session array
        $laravelSession = [
            '_token' => bin2hex(random_bytes(20)),
            'user_id' => $sessionData['user_id'] ?? null,
            'email' => $sessionData['email'] ?? null,
            'name' => $sessionData['name'] ?? null,
            'role' => $sessionData['role'] ?? null,
            'picture' => $sessionData['picture'] ?? null,
            'logged_in' => $sessionData['logged_in'] ?? false,
            '_previous' => ['url' => '/hub.php'],
            '_flash' => ['old' => [], 'new' => []],
        ];
        
        // Serialize as Laravel expects (PHP serialize)
        return serialize($laravelSession);
    }
}
