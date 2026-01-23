<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain as BaseInitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByDomain extends BaseInitializeTenancyByDomain
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Prevent initialization on central domains
            $centralDomains = config('tenancy.domain_identification.central_domains', []);
            $host = $request->getHost();

            if (in_array($host, $centralDomains)) {
                return $next($request);
            }

            // Initialize tenancy
            return parent::handle($request, $next);
        } catch (TenantCouldNotBeIdentifiedException $e) {
            // If tenant cannot be identified, redirect to central domain
            $centralDomain = config('app.url', 'http://localhost');

            // If it's an API request, return JSON error
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Tenant not found',
                    'message' => 'The requested tenant could not be identified.',
                ], 404);
            }

            // Otherwise redirect to central domain with error
            return redirect()->away($centralDomain . '?error=tenant_not_found');
        }
    }
}
