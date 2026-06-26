<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCaptureApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = config('charliemind.capture_api_token');
        $requestToken = $request->bearerToken();

        if (! is_string($configuredToken) || $configuredToken === '' || ! is_string($requestToken) || ! hash_equals($configuredToken, $requestToken)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return $next($request);
    }
}
