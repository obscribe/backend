<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTierLimits
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->isPro()) {
            return $next($request);
        }

        // Free tier limits
        $route = $request->route()->getName();

        if ($route === 'notebooks.store') {
            $notebookCount = $user->notebooks()->whereNull('trashed_at')->count();
            if ($notebookCount >= 1) {
                return response()->json([
                    'message' => 'Free tier is limited to 1 notebook. Upgrade to Pro for unlimited notebooks.',
                    'upgrade_required' => true,
                ], 403);
            }
        }

        if ($route === 'pages.store') {
            $notebookId = $request->route('notebook');
            $pageCount = \App\Models\Page::where('notebook_id', $notebookId)
                ->whereNull('trashed_at')
                ->count();
            if ($pageCount >= 100) {
                return response()->json([
                    'message' => 'Free tier is limited to 100 pages per notebook. Upgrade to Pro for unlimited pages.',
                    'upgrade_required' => true,
                ], 403);
            }
        }

        return $next($request);
    }
}
