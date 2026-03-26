<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'encrypted_vault_key' => ['nullable', 'string'],
            'vault_nonce' => ['nullable', 'string'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'encrypted_vault_key' => $validated['encrypted_vault_key'] ?? null,
            'vault_nonce' => $validated['vault_nonce'] ?? null,
        ]);

        // Send email verification
        EmailVerificationController::sendVerificationEmail($user);

        $token = $user->createToken('auth-token', ['*'], now()->addDays(30));

        // Store request metadata on the token
        $token->accessToken->update([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // If MFA is enabled, return a temporary token for MFA verification
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

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'All sessions revoked.']);
    }
}
