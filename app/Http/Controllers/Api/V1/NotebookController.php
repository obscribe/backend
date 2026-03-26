<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotebookRequest;
use App\Http\Requests\UpdateNotebookRequest;
use App\Models\Notebook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotebookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notebooks = $request->user()->notebooks()
            ->whereNull('trashed_at')
            ->withCount(['pages' => fn ($q) => $q->whereNull('trashed_at')])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'notebooks' => $notebooks->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->title,
                'description' => $n->description,
                'icon' => $n->icon,
                'color' => $n->color,
                'type' => $n->type,
                'is_archived' => $n->is_archived,
                'is_locked' => $n->is_locked,
                'sort_order' => $n->sort_order,
                'page_count' => $n->pages_count,
                'created_at' => $n->created_at,
                'updated_at' => $n->updated_at,
            ]),
        ]);
    }

    public function store(StoreNotebookRequest $request): JsonResponse
    {
        $notebook = $request->user()->notebooks()->create([
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'icon' => $request->validated('icon', '📔'),
            'color' => $request->validated('color', '#5b9a8b'),
            'type' => $request->validated('type', 'permanent'),
        ]);

        return response()->json([
            'notebook' => [
                'id' => $notebook->id,
                'title' => $notebook->title,
                'description' => $notebook->description,
                'icon' => $notebook->icon,
                'color' => $notebook->color,
                'type' => $notebook->type,
                'is_archived' => $notebook->is_archived,
                'is_locked' => $notebook->is_locked,
                'sort_order' => $notebook->sort_order,
                'page_count' => 0,
                'created_at' => $notebook->created_at,
                'updated_at' => $notebook->updated_at,
            ],
        ], 201);
    }

    public function show(Request $request, Notebook $notebook): JsonResponse
    {
        if ($notebook->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $notebook->loadCount(['pages' => fn ($q) => $q->whereNull('trashed_at')]);

        return response()->json([
            'notebook' => [
                'id' => $notebook->id,
                'title' => $notebook->title,
                'description' => $notebook->description,
                'icon' => $notebook->icon,
                'color' => $notebook->color,
                'type' => $notebook->type,
                'is_archived' => $notebook->is_archived,
                'is_locked' => $notebook->is_locked,
                'sort_order' => $notebook->sort_order,
                'page_count' => $notebook->pages_count,
                'created_at' => $notebook->created_at,
                'updated_at' => $notebook->updated_at,
            ],
        ]);
    }

    public function update(UpdateNotebookRequest $request, Notebook $notebook): JsonResponse
    {
        $notebook->update($request->validated());
        $notebook->loadCount(['pages' => fn ($q) => $q->whereNull('trashed_at')]);

        return response()->json([
            'notebook' => [
                'id' => $notebook->id,
                'title' => $notebook->title,
                'description' => $notebook->description,
                'icon' => $notebook->icon,
                'color' => $notebook->color,
                'type' => $notebook->type,
                'is_archived' => $notebook->is_archived,
                'is_locked' => $notebook->is_locked,
                'sort_order' => $notebook->sort_order,
                'page_count' => $notebook->pages_count,
                'created_at' => $notebook->created_at,
                'updated_at' => $notebook->updated_at,
            ],
        ]);
    }

    public function destroy(Request $request, Notebook $notebook): JsonResponse
    {
        if ($notebook->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $notebook->update(['trashed_at' => now()]);
        // Also trash all pages in this notebook
        $notebook->pages()->whereNull('trashed_at')->update(['trashed_at' => now()]);

        return response()->json(['message' => 'Notebook moved to trash.']);
    }

    public function restore(Request $request, Notebook $notebook): JsonResponse
    {
        if ($notebook->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $notebook->update(['trashed_at' => null]);
        // Restore pages that were trashed at the same time
        $notebook->pages()->whereNotNull('trashed_at')->update(['trashed_at' => null]);

        return response()->json(['message' => 'Notebook restored.']);
    }
}
