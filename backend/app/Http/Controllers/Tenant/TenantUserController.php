<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TenantUserController extends Controller
{
    /**
     * Get tenant users.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $users = User::with('roles')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'users' => $users,
            'can_add_user' => $tenant->canAddUser(),
            'max_users' => $tenant->max_users ?? $tenant->getPlanDetailsAttribute()['max_users'],
        ]);
    }

    /**
     * Create a new user for the tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Check if tenant can add more users
        if (!$tenant->canAddUser()) {
            return response()->json([
                'error' => 'User limit exceeded',
                'message' => sprintf(
                    'You have reached your plan limit of %d users. Please upgrade to add more users.',
                    $tenant->max_users ?? $tenant->getPlanDetailsAttribute()['max_users']
                ),
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:author,editor,admin',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Assign role using Spatie permission
        $user->assignRole($request->role);

        // Log activity
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'user_created',
            'model_type' => 'User',
            'model_id' => $user->id,
            'description' => "Created user: {$user->name} ({$user->email})",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user->load('roles'),
        ], 201);
    }

    /**
     * Update a user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => 'sometimes|string|min:8|confirmed',
            'role' => 'sometimes|string|in:author,editor,admin',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update($request->only(['name', 'email']));

        if ($request->filled('password')) {
            $user->update([
                'password' => Hash::make($request->password),
            ]);
        }

        if ($request->filled('role')) {
            // Remove all current roles and assign new one
            $user->syncRoles([$request->role]);
        }

        // Log activity
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'user_updated',
            'model_type' => 'User',
            'model_id' => $user->id,
            'description' => "Updated user: {$user->name}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->load('roles'),
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Prevent deleting the last admin
        if ($user->hasRole('admin')) {
            $adminCount = User::role('admin')->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'error' => 'Cannot delete last admin',
                    'message' => 'You must have at least one admin user.',
                ], 422);
            }
        }

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'error' => 'Cannot delete yourself',
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        $userName = $user->name;
        $user->delete();

        // Log activity
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'user_deleted',
            'model_type' => 'User',
            'model_id' => $user->id,
            'description' => "Deleted user: {$userName}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }
}
