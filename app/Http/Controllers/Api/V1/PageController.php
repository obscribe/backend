<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePageRequest;
use App\Http\Requests\UpdatePageRequest;
use App\Models\Notebook;
use App\Models\Page;
use App\Models\PageTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index(Request $request, Notebook $notebook): JsonResponse
    {
        if ($notebook->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $pages = $notebook->pages()
            ->whereNull('trashed_at')
            ->with('tags')
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'pages' => $pages->map(fn ($p) => $this->formatPage($p)),
        ]);
    }

    public function store(StorePageRequest $request, Notebook $notebook): JsonResponse
    {
        $page = $notebook->pages()->create([
            'title' => $request->validated('title', ''),
            'encrypted_content' => $request->validated('encrypted_content'),
            'content_nonce' => $request->validated('content_nonce'),
            'date_mode' => $request->validated('date_mode', 'undated'),
            'page_date' => $request->validated('page_date'),
            'template_type' => $request->validated('template_type', 'blank'),
        ]);

        if ($tags = $request->validated('tags')) {
            foreach ($tags as $tag) {
                $page->tags()->create(['tag' => $tag]);
            }
        }

        $page->load('tags');
        $notebook->touch();

        return response()->json([
            'page' => $this->formatPage($page),
        ], 201);
    }

    public function show(Request $request, Page $page): JsonResponse
    {
        if ($page->notebook->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $page->load('tags');

        return response()->json([
            'page' => $this->formatPage($page, true),
        ]);
    }

    public function update(UpdatePageRequest $request, Page $page): JsonResponse
    {
        $page->update($request->safe()->except('tags'));

        if ($request->has('tags')) {
            $page->tags()->delete();
            foreach ($request->validated('tags') ?? [] as $tag) {
                $page->tags()->create(['tag' => $tag]);
            }
        }

        $page->load('tags');
        $page->notebook->touch();

        return response()->json([
            'page' => $this->formatPage($page, true),
        ]);
    }

    public function destroy(Request $request, Page $page): JsonResponse
    {
        if ($page->notebook->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $page->update(['trashed_at' => now(), 'is_pinned' => false]);

        return response()->json(['message' => 'Page moved to trash.']);
    }

    public function restore(Request $request, Page $page): JsonResponse
    {
        if ($page->notebook->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $page->update(['trashed_at' => null]);

        return response()->json(['message' => 'Page restored.']);
    }

    public function togglePin(Request $request, Page $page): JsonResponse
    {
        if ($page->notebook->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $page->update(['is_pinned' => !$page->is_pinned]);

        return response()->json([
            'is_pinned' => $page->is_pinned,
        ]);
    }

    public function toggleFavorite(Request $request, Page $page): JsonResponse
    {
        if ($page->notebook->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $page->update(['is_favorited' => !$page->is_favorited]);

        return response()->json([
            'is_favorited' => $page->is_favorited,
        ]);
    }

    private function formatPage(Page $page, bool $includeContent = false): array
    {
        $data = [
            'id' => $page->id,
            'notebook_id' => $page->notebook_id,
            'title' => $page->title,
            'date_mode' => $page->date_mode,
            'page_date' => $page->page_date?->format('Y-m-d'),
            'template_type' => $page->template_type,
            'is_pinned' => $page->is_pinned,
            'is_favorited' => $page->is_favorited,
            'word_count' => $page->word_count ?? 0,
            'sort_order' => $page->sort_order ?? 0,
            'tags' => $page->tags->pluck('tag')->toArray(),
            'created_at' => $page->created_at,
            'updated_at' => $page->updated_at,
        ];

        if ($includeContent) {
            $data['encrypted_content'] = $page->encrypted_content;
            $data['content_nonce'] = $page->content_nonce;
        }

        return $data;
    }
}
