<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedQuery;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedQueryController extends Controller
{
    /**
     * List saved query snippets.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id' => ['nullable', 'string', 'uuid'],
            'search' => ['nullable', 'string'],
            'tag' => ['nullable', 'string'],
        ]);

        $query = SavedQuery::query()
            ->with(['user:id,name,email', 'connection:id,name,driver'])
            ->latest();

        if (!empty($validated['workspace_id'])) {
            $query->where('workspace_id', $validated['workspace_id']);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('query_text', 'like', "%{$search}%");
            });
        }

        if (!empty($validated['tag'])) {
            $tag = $validated['tag'];
            $query->whereJsonContains('tags', $tag);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    /**
     * Store a new saved query snippet.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'string', 'uuid'],
            'connection_id' => ['nullable', 'string', 'uuid'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'query_text' => ['required', 'string'],
            'tags' => ['nullable', 'array'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user() ?? User::query()->firstOrFail();

        $savedQuery = SavedQuery::create([
            'workspace_id' => $validated['workspace_id'],
            'connection_id' => $validated['connection_id'] ?? null,
            'user_id' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'query_text' => $validated['query_text'],
            'tags' => $validated['tags'] ?? [],
            'is_public' => $validated['is_public'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Snippet de consulta guardado exitosamente.',
            'data' => $savedQuery,
        ], 201);
    }

    /**
     * Update an existing saved query snippet.
     */
    public function update(Request $request, SavedQuery $savedQuery): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'query_text' => ['sometimes', 'required', 'string'],
            'tags' => ['nullable', 'array'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $savedQuery->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Snippet actualizado exitosamente.',
            'data' => $savedQuery,
        ]);
    }

    /**
     * Delete a saved query snippet.
     */
    public function destroy(SavedQuery $savedQuery): JsonResponse
    {
        $savedQuery->delete();

        return response()->json([
            'success' => true,
            'message' => 'Snippet eliminado correctamente.',
        ]);
    }
}
