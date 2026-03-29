<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip email verification requirement in self-hosted mode
        if (config('app.self_hosted')) {
            return $next($request);
        }

        if (!$request->user() || !$request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email address must be verified.',
            ], 403);
        }

        return $next($request);
    }
}
