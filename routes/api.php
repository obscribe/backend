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
    ->middleware(['auth:sanctum', 'throttle:5,1']);

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

// These need auth but NOT verified/mfa (used during registration/onboarding)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/auth/resend-verification', [EmailVerificationController::class, 'resend']);
    
    // Vault — must be accessible right after registration (before email verification)
    Route::get('/vault', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        return response()->json([
            'encrypted_vault_key' => $user->encrypted_vault_key,
            'vault_nonce' => $user->vault_nonce,
            'salt' => $user->salt,
            'recovery_encrypted_vault_key' => $user->recovery_encrypted_vault_key,
            'recovery_vault_nonce' => $user->recovery_vault_nonce,
        ]);
    });
    Route::patch('/vault', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'encrypted_vault_key' => ['required', 'string'],
            'vault_nonce' => ['required', 'string'],
            'salt' => ['sometimes', 'string'],
            'recovery_encrypted_vault_key' => ['sometimes', 'string'],
            'recovery_vault_nonce' => ['sometimes', 'string'],
        ]);
        $request->user()->update($validated);
        return response()->json(['message' => 'Vault key updated.']);
    });
});

Route::middleware(['auth:sanctum', 'verified', 'mfa.required'])->group(function () {

    // Auth actions
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);

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
    Route::post('notebooks', [NotebookController::class, 'store'])->middleware('tier.limits');
    Route::apiResource('notebooks', NotebookController::class)->except(['store']);
    Route::post('notebooks/{notebook}/restore', [NotebookController::class, 'restore']);
    Route::patch('notebooks/{notebook}/favorite', [NotebookController::class, 'toggleFavorite']);

    // Pages
    Route::get('notebooks/{notebook}/pages', [PageController::class, 'index']);
    Route::post('notebooks/{notebook}/pages', [PageController::class, 'store']);
    Route::get('pages/{page}', [PageController::class, 'show']);
    Route::patch('pages/{page}', [PageController::class, 'update']);
    Route::delete('pages/{page}', [PageController::class, 'destroy']);
    Route::post('pages/{page}/restore', [PageController::class, 'restore']);
    Route::patch('pages/{page}/pin', [PageController::class, 'togglePin']);
    Route::patch('pages/{page}/favorite', [PageController::class, 'toggleFavorite']);

    // Favorites
    Route::get('favorites', function (Request $request) {
        $pages = \App\Models\Page::whereHas('notebook', function ($q) use ($request) {
            $q->where('user_id', $request->user()->id);
        })->where('is_favorited', true)
          ->whereNull('trashed_at')
          ->with('notebook:id,title,icon')
          ->orderByDesc('updated_at')
          ->get();

        return response()->json(['pages' => $pages]);
    });

    // Search
    Route::get('search', function (Request $request) {
        $q = $request->input('q', '');
        if (strlen($q) < 2) {
            return response()->json(['notebooks' => [], 'pages' => []]);
        }

        $userId = $request->user()->id;
        $escaped = str_replace(['%', '_'], ['\%', '\_'], $q);

        $notebooks = \App\Models\Notebook::where('user_id', $userId)
            ->whereNull('trashed_at')
            ->where(function ($query) use ($escaped) {
                $query->where('title', 'like', "%{$escaped}%")
                      ->orWhere('description', 'like', "%{$escaped}%");
            })->limit(10)->get();

        // Note: content is E2E encrypted so we only search titles and tags server-side
        $pages = \App\Models\Page::whereHas('notebook', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->whereNull('trashed_at')
          ->where(function ($query) use ($escaped) {
              $query->where('title', 'like', "%{$escaped}%")
                    ->orWhereHas('tags', function ($tq) use ($escaped) {
                        $tq->where('tag', 'like', "%{$escaped}%");
                    });
          })->with('notebook:id,title,icon')
            ->limit(10)->get();

        return response()->json(['notebooks' => $notebooks, 'pages' => $pages]);
    });

    // Vault routes moved outside verified group (see above)
});
