<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QueryHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueryHistoryController extends Controller
{
    /**
     * Get paginated query execution history with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id' => ['nullable', 'string', 'uuid'],
            'connection_id' => ['nullable', 'string', 'uuid'],
            'status' => ['nullable', 'in:success,error'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = QueryHistory::query()
            ->with(['connection:id,name,driver,database_name', 'user:id,name,email'])
            ->latest('executed_at');

        if (!empty($validated['workspace_id'])) {
            $query->where('workspace_id', $validated['workspace_id']);
        }

        if (!empty($validated['connection_id'])) {
            $query->where('connection_id', $validated['connection_id']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where('query_text', 'like', "%{$search}%");
        }

        $perPage = (int) ($validated['per_page'] ?? 30);
        $history = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $history->items(),
            'pagination' => [
                'current_page' => $history->currentPage(),
                'per_page' => $history->perPage(),
                'total_rows' => $history->total(),
                'total_pages' => $history->lastPage(),
            ],
        ]);
    }

    /**
     * Clear all query history for the current user/workspace.
     */
    public function clear(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'string', 'uuid'],
            'connection_id' => ['nullable', 'string', 'uuid'],
        ]);

        $query = QueryHistory::query()->where('workspace_id', $validated['workspace_id']);

        if (!empty($validated['connection_id'])) {
            $query->where('connection_id', $validated['connection_id']);
        }

        $deletedCount = $query->delete();

        return response()->json([
            'success' => true,
            'message' => "Se eliminaron {$deletedCount} registros del historial de consultas.",
        ]);
    }
}
