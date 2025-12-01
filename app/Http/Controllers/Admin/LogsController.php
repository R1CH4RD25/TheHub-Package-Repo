<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LogsController extends Controller
{
    /**
     * Display activity logs interface.
     */
    public function index(Request $request)
    {
        $currentUser = $request->attributes->get('user');
        $isSuperAdmin = ($currentUser['role'] === 'super_admin');

        if (!$isSuperAdmin) {
            abort(403, 'Only super admins can access activity logs');
        }

        return view('admin.logs', [
            'isSuperAdmin' => $isSuperAdmin
        ]);
    }

    /**
     * Get audit logs with filtering.
     */
    public function list(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 100);
        $offset = (int) $request->query('offset', 0);
        $action = $request->query('action');
        $table = $request->query('table');
        $userId = $request->query('user_id');

        try {
            $query = AuditLog::with('user:id,name,email');

            // Apply filters
            if ($action) {
                $query->where('action', $action);
            }

            if ($table) {
                $query->where('table_name', $table);
            }

            if ($userId) {
                $query->where('user_id', (int) $userId);
            }

            // Get total count
            $total = $query->count();

            // Get paginated results
            $logs = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'user_id' => $log->user_id,
                        'user_name' => $log->user->name ?? 'Unknown',
                        'user_email' => $log->user->email ?? '',
                        'action' => $log->action,
                        'table_name' => $log->table_name,
                        'record_id' => $log->record_id,
                        'old_values' => $log->old_values,
                        'new_values' => $log->new_values,
                        'ip_address' => $log->ip_address,
                        'user_agent' => $log->user_agent,
                        'created_at' => $log->created_at->toDateTimeString(),
                    ];
                });

            return response()->json([
                'logs' => $logs,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load logs: ' . $e->getMessage()], 500);
        }
    }
}
