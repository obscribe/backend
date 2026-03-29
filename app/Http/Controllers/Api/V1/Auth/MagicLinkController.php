<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\MagicLinkNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'If an account exists, a magic link has been sent.',
            ]);
        }

        $token = Str::random(64);
        Cache::put("magic-link:{$token}", $user->id, now()->addMinutes(15));

        $user->notify(new MagicLinkNotification($token));

        return response()->json([
            'message' => 'Magic link sent! Check your email.',
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $userId = Cache::pull("magic-link:{$validated['token']}");

        if (!$userId) {
            return response()->json([
                'message' => 'Invalid or expired magic link.',
            ], 401);
        }

        $user = User::findOrFail($userId);

        // Mark email as verified if not already
        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // If MFA is enabled, require MFA verification
        if ($user->hasMfaEnabled()) {
            $mfaToken = $user->createToken('mfa-pending', ['mfa:verify'], now()->addMinutes(5));
            $mfaToken->accessToken->update([
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'mfa_required' => true,
                'mfa_token' => $mfaToken->plainTextToken,
            ]);
        }

        $token = $user->createToken('auth-token', ['*'], now()->addDays(30));
        $token->accessToken->update([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken,
        ]);
    }
}
