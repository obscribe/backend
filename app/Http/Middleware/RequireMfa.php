<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Check if token only has mfa:verify ability (pending MFA)
        $token = $user->currentAccessToken();
        if ($token && !$token->can('*') && $token->can('mfa:verify')) {
            return response()->json([
                'message' => 'MFA verification required.',
                'mfa_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
