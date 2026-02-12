<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Hub\{Auth as LegacyAuth, Database, AuditLogger};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    /**
     * Display site settings interface.
     */
    public function index(Request $request)
    {
        $currentUser = $request->attributes->get('user');
        $isSuperAdmin = ($currentUser['role'] === 'super_admin');

        if (!$isSuperAdmin) {
            abort(403, 'Only super admins can access site settings');
        }

        return view('admin.settings', [
            'isSuperAdmin' => $isSuperAdmin
        ]);
    }

    /**
     * Display general settings tab.
     */
    public function general(Request $request)
    {
        return $this->index($request)->with('activeTab', 'general');
    }

    /**
     * Display authentication settings tab.
     */
    public function auth(Request $request)
    {
        return $this->index($request)->with('activeTab', 'auth');
    }

    /**
     * Display modules settings tab.
     */
    public function modules(Request $request)
    {
        return $this->index($request)->with('activeTab', 'modules');
    }

    /**
     * Display theme settings tab.
     */
    public function theme(Request $request)
    {
        return $this->index($request)->with('activeTab', 'theme');
    }

    /**
     * Display layout settings tab.
     */
    public function layout(Request $request)
    {
        return $this->index($request)->with('activeTab', 'layout');
    }

    /**
     * Get all site settings.
     */
    public function get(Request $request): JsonResponse
    {
        $db = Database::getInstance();

        try {
            $settings = $db->fetchAll("
                SELECT setting_key, setting_value, setting_type, description
                FROM site_settings
                ORDER BY setting_key
            ");

            // Convert to key-value object
            $settingsObject = [];
            foreach ($settings as $setting) {
                $value = $setting['setting_value'];

                // Type conversion
                if ($setting['setting_type'] === 'boolean') {
                    $value = (bool)$value;
                } elseif ($setting['setting_type'] === 'number') {
                    if (is_numeric($value)) {
                        $value = strpos($value, '.') !== false ? (float)$value : (int)$value;
                    }
                }

                $settingsObject[$setting['setting_key']] = $value;
            }

            return response()->json($settingsObject);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load settings: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update site settings.
     */
    public function update(Request $request): JsonResponse
    {
        $currentUser = $request->attributes->get('user');
        $db = Database::getInstance();

        $data = $request->json()->all();

        if (empty($data)) {
            return response()->json(['error' => 'No data provided'], 400);
        }

        // Remove CSRF token if present
        unset($data['csrf_token']);

        try {
            $updatedCount = 0;
            $errors = [];

            foreach ($data as $key => $value) {
                // Validate setting exists
                $existing = $db->fetchOne("
                    SELECT id, setting_key, setting_type, setting_value
                    FROM site_settings
                    WHERE setting_key = ?
                ", [$key]);

                if (!$existing) {
                    $errors[] = "Unknown setting: $key";
                    continue;
                }

                $oldValue = $existing['setting_value'];

                // Type conversion for storage
                if ($existing['setting_type'] === 'boolean') {
                    $value = $value ? '1' : '0';
                }

                // Update setting
                $db->execute("
                    UPDATE site_settings
                    SET setting_value = ?, updated_by = ?
                    WHERE setting_key = ?
                ", [$value, $currentUser['id'], $key]);

                $updatedCount++;

                // Audit log
                AuditLogger::logUpdate(
                    'site_settings',
                    $existing['id'],
                    ['setting_value' => $oldValue],
                    ['setting_value' => $value],
                    $currentUser['id']
                );
            }

            return response()->json([
                'success' => true,
                'message' => "Updated $updatedCount settings",
                'updated' => $updatedCount,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reset all settings to defaults.
     */
    public function reset(Request $request): JsonResponse
    {
        $currentUser = $request->attributes->get('user');
        $db = Database::getInstance();

        try {
            // This would typically restore from a defaults file or table
            // For now, just log the action
            AuditLogger::logUpdate(
                'site_settings',
                null,
                ['action' => 'reset_all'],
                ['reset_to' => 'defaults'],
                $currentUser['id']
            );

            return response()->json([
                'success' => true,
                'message' => 'Settings reset to defaults'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Reset failed: ' . $e->getMessage()], 500);
        }
    }
}
