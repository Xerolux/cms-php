<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantLimits
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!tenancy()->initialized) {
            return $next($request);
        }

        $tenant = tenant();

        if (!$tenant) {
            return $next($request);
        }

        // Check specific limits based on the route
        $route = $request->route();

        if ($route) {
            $routeName = $route->getName();

            // Check user limit
            if (in_array($routeName, ['users.store', 'users.create']) && !$tenant->canAddUser()) {
                return response()->json([
                    'error' => 'User limit exceeded',
                    'message' => sprintf(
                        'You have reached your plan limit of %d users. Please upgrade to add more users.',
                        $tenant->max_users ?? $tenant->getPlanDetailsAttribute()['max_users']
                    ),
                ], 403);
            }

            // Check post limit
            if (in_array($routeName, ['posts.store', 'posts.create']) && !$tenant->canAddPost()) {
                return response()->json([
                    'error' => 'Post limit exceeded',
                    'message' => sprintf(
                        'You have reached your plan limit of %d posts. Please upgrade to create more posts.',
                        $tenant->max_posts ?? $tenant->getPlanDetailsAttribute()['max_posts']
                    ),
                ], 403);
            }

            // Check feature access
            $requiredFeature = $request->route()->getAction('required_feature') ?? null;
            if ($requiredFeature && !$tenant->hasFeature($requiredFeature)) {
                return response()->json([
                    'error' => 'Feature not available',
                    'message' => sprintf(
                        'The "%s" feature is not available in your current plan. Please upgrade to access this feature.',
                        $requiredFeature
                    ),
                ], 403);
            }
        }

        return $next($request);
    }
}
