<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UserManagementController extends Controller
{
    /**
     * List all users (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($users);
    }

    /**
     * Create a new user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_admin' => $validated['is_admin'] ?? false,
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'User created.',
            'user' => $user->only(['id', 'name', 'email', 'is_admin', 'email_verified_at', 'created_at']),
        ], 201);
    }

    /**
     * Update a user.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'User updated.',
            'user' => $user->only(['id', 'name', 'email', 'is_admin', 'email_verified_at', 'created_at', 'updated_at']),
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($request->user()->id === $id) {
            return response()->json([
                'message' => 'You cannot delete your own account from the admin panel.',
            ], 422);
        }

        $user = User::findOrFail($id);

        // Revoke all tokens
        $user->tokens()->delete();

        // Delete related data
        $user->notebooks()->each(function ($notebook) {
            $notebook->pages()->forceDelete();
            $notebook->forceDelete();
        });

        $user->delete();

        return response()->json([
            'message' => 'User deleted.',
        ]);
    }

    /**
     * Reset a user's password (admin action, for no-email mode).
     */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'password' => ['required', 'string', Password::min(8)],
        ]);

        $user->update(['password' => $validated['password']]);

        // Revoke all existing tokens for the user
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password has been reset. The user will need to log in again.',
        ]);
    }
}
