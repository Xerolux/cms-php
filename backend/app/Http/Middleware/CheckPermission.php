<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $user = Auth::user();

        // Super Admin hat alle Rechte
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // Rollenbasierte Berechtigungen
        if (!$this->hasPermission($user, $permission)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'You do not have permission to perform this action',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Prüft ob User die benötigte Permission hat
     */
    protected function hasPermission($user, string $permission): bool
    {
        $rolePermissions = $this->getRolePermissions($user->role);

        return in_array($permission, $rolePermissions) || in_array('*', $rolePermissions);
    }

    /**
     * Gibt Permissions für eine Rolle zurück
     */
    protected function getRolePermissions(string $role): array
    {
        return match($role) {
            'super_admin' => ['*'],
            'admin' => [
                'create-posts', 'edit-posts', 'delete-posts',
                'create-categories', 'edit-categories', 'delete-categories',
                'create-tags', 'edit-tags', 'delete-tags',
                'upload-media', 'delete-media',
                'create-users', 'edit-users', 'delete-users',
                'manage-pages', 'manage-settings',
            ],
            'editor' => [
                'create-posts', 'edit-posts', // Alle Posts
                'upload-media', 'delete-media',
            ],
            'author' => [
                'create-posts', 'edit-own-posts',
                'upload-media', 'delete-own-media',
            ],
            'contributor' => [
                'create-posts', // Nur Drafts
            ],
            'subscriber' => [],
            default => [],
        };
    }
}
