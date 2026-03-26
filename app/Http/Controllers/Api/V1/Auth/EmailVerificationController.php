<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EmailVerificationController extends Controller
{
    /**
     * Verify an email address using a token from the verification email.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
        ]);

        $cacheKey = 'email_verification:' . $validated['email'];
        $storedToken = Cache::get($cacheKey);

        if (!$storedToken || !hash_equals($storedToken, $validated['token'])) {
            return response()->json([
                'message' => 'Invalid or expired verification token.',
            ], 422);
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        if ($user->email_verified_at) {
            Cache::forget($cacheKey);
            return response()->json([
                'message' => 'Email already verified.',
                'user' => $user,
            ]);
        }

        $user->email_verified_at = now();
        $user->save();

        Cache::forget($cacheKey);

        return response()->json([
            'message' => 'Email verified successfully.',
            'user' => $user,
        ]);
    }

    /**
     * Resend the verification email for the authenticated user.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email already verified.',
            ]);
        }

        self::sendVerificationEmail($user);

        return response()->json([
            'message' => 'Verification email sent.',
        ]);
    }

    /**
     * Generate a verification token, cache it, and send the notification.
     */
    public static function sendVerificationEmail(User $user): void
    {
        $token = Str::random(64);
        $cacheKey = 'email_verification:' . $user->email;

        Cache::put($cacheKey, $token, now()->addHours(24));

        $user->notify(new VerifyEmailNotification($token));
    }
}
