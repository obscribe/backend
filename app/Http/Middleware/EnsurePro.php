<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePro
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->isPro()) {
            return response()->json([
                'message' => 'This feature requires Obscribe Pro.',
                'upgrade_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
