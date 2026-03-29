<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailVerified::class,
            'tier.limits' => \App\Http\Middleware\EnforceTierLimits::class,
            'mfa.required' => \App\Http\Middleware\RequireMfa::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'pro' => \App\Http\Middleware\EnsurePro::class,
            'billing' => \App\Http\Middleware\BillingEnabled::class,
        ]);

        // $middleware->statefulApi(); // Using token auth, not session/CSRF
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->expectsJson() && !config('app.debug')) {
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                return response()->json(['message' => 'Server error.'], $status);
            }
        });
    })->create();
