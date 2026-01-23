<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains as BasePreventAccessFromCentralDomains;
use Symfony\Component\HttpFoundation\Response;

class PreventAccessFromCentralDomains extends BasePreventAccessFromCentralDomains
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $onCentralDomain = parent::handle($request, $next);

        if ($onCentralDomain instanceof Response) {
            return $onCentralDomain;
        }

        // Check if tenant subscription is active
        if (tenancy()->initialized) {
            $tenant = tenant();

            if (!$tenant || !$tenant->hasActiveSubscription()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'Subscription inactive',
                        'message' => 'Your subscription has expired. Please renew to continue.',
                    ], 403);
                }

                return redirect()->route('subscription.expired');
            }

            // Check if tenant has exceeded storage limit
            if ($tenant->hasExceededStorageLimit()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'Storage limit exceeded',
                        'message' => 'You have exceeded your storage limit. Please upgrade your plan.',
                    ], 403);
                }

                return redirect()->route('subscription.storage_exceeded');
            }
        }

        return $next($request);
    }
}
