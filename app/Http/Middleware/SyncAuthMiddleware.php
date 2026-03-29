<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncAuthMiddleware
{
    /**
     * Verify that inbound sync requests from Kashtre carry the correct shared secret.
     *
     * Kashtre must send:  Authorization: Bearer <CALLING_SERVICE_SYNC_SECRET>
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.calling_service.sync_secret');

        if (!$secret) {
            // Secret not configured — fail closed in production, allow in local/testing
            if (!app()->isLocal() && !app()->runningUnitTests()) {
                return response()->json(['error' => 'Sync secret not configured on this server.'], 500);
            }
        } else {
            $provided = $request->bearerToken();

            if (!$provided || !hash_equals($secret, $provided)) {
                return response()->json(['error' => 'Unauthorized.'], 401);
            }
        }

        return $next($request);
    }
}
