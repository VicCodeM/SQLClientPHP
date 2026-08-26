<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Get paginated audit logs with action and target filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id' => ['nullable', 'string', 'uuid'],
            'connection_id' => ['nullable', 'string', 'uuid'],
            'action' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = AuditLog::query()
            ->with(['user:id,name,email', 'connection:id,name,driver'])
            ->latest('created_at');

        if (!empty($validated['workspace_id'])) {
            $query->where('workspace_id', $validated['workspace_id']);
        }

        if (!empty($validated['connection_id'])) {
            $query->where('connection_id', $validated['connection_id']);
        }

        if (!empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        $perPage = (int) ($validated['per_page'] ?? 30);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total_rows' => $logs->total(),
                'total_pages' => $logs->lastPage(),
            ],
        ]);
    }
}
