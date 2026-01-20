<?php

namespace App\Http\Middleware;

use App\Services\SessionManagementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackSessionActivity
{
    protected SessionManagementService $sessionService;

    public function __construct(SessionManagementService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $tokenId = $user->currentAccessToken()->id;
            $this->sessionService->updateSessionActivity($tokenId);
        }

        return $next($request);
    }
}
