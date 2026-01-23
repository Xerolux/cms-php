<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TenantSettingsController extends Controller
{
    /**
     * Get tenant settings.
     */
    public function show(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        return response()->json([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'email' => $tenant->email,
            'plan' => $tenant->plan,
            'settings' => $tenant->settings ?? [],
            'features' => $tenant->features ?? [],
            'is_active' => $tenant->is_active,
            'max_users' => $tenant->max_users,
            'max_storage_gb' => $tenant->max_storage_gb,
            'max_posts' => $tenant->max_posts,
            'plan_details' => $tenant->getPlanDetailsAttribute(),
        ]);
    }

    /**
     * Update tenant settings.
     */
    public function update(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:tenants,email,' . $tenant->id,
            'settings' => 'sometimes|array',
            'settings.timezone' => 'sometimes|string|timezone',
            'settings.locale' => 'sometimes|string|in:en,de,fr,es,it',
            'settings.site_name' => 'sometimes|string|max:255',
            'settings.site_description' => 'sometimes|string|max:500',
            'settings.site_logo' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tenant->update($request->only(['name', 'email', 'settings']));

        return response()->json([
            'message' => 'Tenant settings updated successfully',
            'tenant' => $tenant,
        ]);
    }
}
