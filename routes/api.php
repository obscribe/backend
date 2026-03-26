<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\MagicLinkController;
use App\Http\Controllers\Api\V1\Auth\MfaController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\NotebookController;
use App\Http\Controllers\Api\V1\PageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (no auth required)
|--------------------------------------------------------------------------
*/

// Auth
Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:register');
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

// Magic Link
Route::post('/auth/magic-link', [MagicLinkController::class, 'send'])
    ->middleware('throttle:login');
Route::get('/auth/magic-link/verify', [MagicLinkController::class, 'verify']);

// Password Reset
Route::post('/auth/forgot-password', [PasswordController::class, 'forgot'])
    ->middleware('throttle:login');
Route::post('/auth/reset-password', [PasswordController::class, 'reset']);

// Email Verification (token in URL, no auth needed)
Route::post('/auth/verify-email', [EmailVerificationController::class, 'verify']);

// MFA Verification (uses temporary mfa-pending token)
Route::post('/auth/mfa/verify', [MfaController::class, 'verify'])
    ->middleware('auth:sanctum');

// System
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
    ]);
});

Route::get('/config', function () {
    return response()->json([
        'app_name' => config('app.name'),
        'features' => [
            'magic_link' => true,
            'mfa' => true,
            'billing' => false, // not configured yet
        ],
    ]);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'mfa.required'])->group(function () {

    // Auth actions
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
    Route::post('/auth/resend-verification', [EmailVerificationController::class, 'resend']);

    // MFA management
    Route::post('/auth/mfa/enable', [MfaController::class, 'enable']);
    Route::post('/auth/mfa/confirm', [MfaController::class, 'confirm']);
    Route::post('/auth/mfa/disable', [MfaController::class, 'disable']);
    Route::get('/auth/mfa/recovery-codes', [MfaController::class, 'recoveryCodes']);
    Route::post('/auth/mfa/recovery-codes/regenerate', [MfaController::class, 'regenerateRecoveryCodes']);

    // Password change (authenticated)
    Route::patch('/user/password', [PasswordController::class, 'change']);

    // User profile
    Route::get('/user', [UserController::class, 'show']);
    Route::patch('/user', [UserController::class, 'update']);
    Route::patch('/user/email', [UserController::class, 'updateEmail']);
    Route::delete('/user', [UserController::class, 'destroy']);

    // Sessions
    Route::get('/user/sessions', function (Request $request) {
        $tokens = $request->user()->tokens()
            ->select('id', 'name', 'ip_address', 'user_agent', 'location', 'last_used_at', 'created_at')
            ->orderByDesc('last_used_at')
            ->get();

        $currentTokenId = $request->user()->currentAccessToken()->id;

        return response()->json([
            'sessions' => $tokens->map(fn ($t) => [
                ...$t->toArray(),
                'is_current' => $t->id === $currentTokenId,
            ]),
        ]);
    });

    Route::delete('/user/sessions/{id}', function (Request $request, $id) {
        $token = $request->user()->tokens()->findOrFail($id);
        if ($token->id === $request->user()->currentAccessToken()->id) {
            return response()->json(['message' => 'Cannot revoke current session. Use logout instead.'], 422);
        }
        $token->delete();
        return response()->json(['message' => 'Session revoked.']);
    });

    Route::delete('/user/sessions', function (Request $request) {
        $request->user()->tokens()
            ->where('id', '!=', $request->user()->currentAccessToken()->id)
            ->delete();
        return response()->json(['message' => 'All other sessions revoked.']);
    });

    // Preferences
    Route::get('/user/preferences', function (Request $request) {
        $user = $request->user();
        return response()->json([
            'theme' => $user->theme,
            'timezone' => $user->timezone,
        ]);
    });

    Route::patch('/user/preferences', function (Request $request) {
        $validated = $request->validate([
            'theme' => ['sometimes', 'string', 'in:light,dark'],
            'timezone' => ['sometimes', 'string', 'max:64'],
        ]);
        $request->user()->update($validated);
        return response()->json([
            'theme' => $request->user()->theme,
            'timezone' => $request->user()->timezone,
        ]);
    });

    // Onboarding
    Route::post('/user/onboarded', function (Request $request) {
        $user = $request->user();
        if (!$user->onboarded_at) {
            $user->onboarded_at = now();
            $user->save();
        }
        return response()->json(['message' => 'Onboarding complete.', 'onboarded_at' => $user->onboarded_at]);
    });

    // Notebooks
    Route::apiResource('notebooks', NotebookController::class);
    Route::post('notebooks/{notebook}/restore', [NotebookController::class, 'restore']);

    // Pages
    Route::get('notebooks/{notebook}/pages', [PageController::class, 'index']);
    Route::post('notebooks/{notebook}/pages', [PageController::class, 'store']);
    Route::get('pages/{page}', [PageController::class, 'show']);
    Route::patch('pages/{page}', [PageController::class, 'update']);
    Route::delete('pages/{page}', [PageController::class, 'destroy']);
    Route::post('pages/{page}/restore', [PageController::class, 'restore']);
    Route::patch('pages/{page}/pin', [PageController::class, 'togglePin']);
    Route::patch('pages/{page}/favorite', [PageController::class, 'toggleFavorite']);

    // Vault
    Route::get('/vault', function (Request $request) {
        $user = $request->user();
        return response()->json([
            'encrypted_vault_key' => $user->encrypted_vault_key,
            'vault_nonce' => $user->vault_nonce,
        ]);
    });

    Route::patch('/vault', function (Request $request) {
        $validated = $request->validate([
            'encrypted_vault_key' => ['required', 'string'],
            'vault_nonce' => ['required', 'string'],
        ]);
        $request->user()->update($validated);
        return response()->json(['message' => 'Vault key updated.']);
    });
});
