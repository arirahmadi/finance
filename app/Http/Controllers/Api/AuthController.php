<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user and return a token.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 201);
    }

    /**
     * Log in a user and return a token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan salah.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    /**
     * Log out a user (revoke token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Get the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    /**
     * Get list of all users (owner-only).
     */
    public function users(Request $request): JsonResponse
    {
        if (strtolower($request->user()->role) !== 'owner') {
            return response()->json(['message' => 'Unauthorized. Owner only.'], 403);
        }

        $users = User::orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Update user role or information (owner-only).
     */
    public function updateUserRole(Request $request, $id): JsonResponse
    {
        if (strtolower($request->user()->role) !== 'owner') {
            return response()->json(['message' => 'Unauthorized. Owner only.'], 403);
        }

        $request->validate([
            'role' => ['required', 'string', 'in:owner,staff'],
        ]);

        $user = User::findOrFail($id);
        $user->role = $request->role;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Role user berhasil diperbarui.',
            'user' => $user,
        ]);
    }

    /**
     * Create a new user (owner-only).
     */
    public function storeUser(Request $request): JsonResponse
    {
        if (strtolower($request->user()->role) !== 'owner') {
            return response()->json(['message' => 'Unauthorized. Owner only.'], 403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:owner,staff'],
            'permissions' => ['nullable', 'array'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'permissions' => $request->permissions ?? [
                'view_transactions',
                'create_transactions',
                'edit_transactions',
                'delete_transactions',
                'view_coa',
                'approve_transactions'
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User baru berhasil ditambahkan.',
            'user' => $user,
        ], 201);
    }

    /**
     * Full update user (owner-only).
     */
    public function updateUser(Request $request, $id): JsonResponse
    {
        if (strtolower($request->user()->role) !== 'owner') {
            return response()->json(['message' => 'Unauthorized. Owner only.'], 403);
        }

        $user = User::findOrFail($id);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $id],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:owner,staff'],
            'permissions' => ['nullable', 'array'],
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->role = $request->role;
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        if ($request->has('permissions')) {
            $user->permissions = $request->permissions;
        }
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Data user berhasil diperbarui.',
            'user' => $user,
        ]);
    }

    /**
     * Reset Password (Forgot Password)
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Alamat email tidak terdaftar dalam sistem.',
            ], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi berhasil disetel ulang.',
        ]);
    }
}
