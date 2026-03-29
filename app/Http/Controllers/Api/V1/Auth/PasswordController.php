<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordController extends Controller
{
    public function forgot(Request $request): JsonResponse
    {
        if (!mailEnabled()) {
            return response()->json([
                'message' => 'Password reset via email is not available. Contact your administrator.',
            ], 422);
        }

        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($request->only('email'));

        // Always return success to prevent email enumeration
        return response()->json([
            'message' => 'If an account exists, a password reset link has been sent.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', PasswordRule::min(8)->mixedCase()->numbers(), 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke all existing tokens
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Invalid or expired reset token.',
            ], 422);
        }

        return response()->json([
            'message' => 'Password has been reset successfully.',
        ]);
    }

    public function change(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', PasswordRule::min(8)->mixedCase()->numbers(), 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 403);
        }

        $user->update(['password' => $validated['password']]);

        // Revoke all other tokens
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
}
