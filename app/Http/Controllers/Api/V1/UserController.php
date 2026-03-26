<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'theme' => ['sometimes', 'string', 'in:light,dark'],
        ]);

        $request->user()->update($validated);

        return response()->json([
            'user' => $request->user()->fresh(),
        ]);
    }

    public function updateEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid password.',
            ], 403);
        }

        $user->update([
            'pending_email' => $validated['email'],
        ]);

        // TODO: Send verification email to new address
        // Mail::to($validated['email'])->send(new VerifyNewEmail($user));

        return response()->json([
            'message' => 'Verification email sent to new address.',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid password.',
            ], 403);
        }

        // Revoke all tokens
        $user->tokens()->delete();

        // Delete all user data (cascading deletes handle notebooks -> pages -> tags, etc.)
        $user->notebooks()->each(function ($notebook) {
            $notebook->pages()->each(function ($page) {
                $page->tags()->delete();
                $page->revisions()->delete();
                $page->searchTokens()->delete();
            });
            $notebook->pages()->delete();
        });
        $user->notebooks()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully.',
        ]);
    }
}
