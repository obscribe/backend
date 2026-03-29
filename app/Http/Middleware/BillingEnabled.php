<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BillingEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.self_hosted')) {
            return response()->json([
                'message' => 'Billing is not available in self-hosted mode.',
            ], 404);
        }

        return $next($request);
    }
}
