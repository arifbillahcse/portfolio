<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBlogPublisher
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hash = config('publishing.token_sha256');
        if (! is_string($hash) || ! preg_match('/\A[a-f0-9]{64}\z/D', $hash)) {
            return response()->json(['message' => 'Blog publishing is not configured.'], 503)
                ->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow');
        }
        if (app()->environment('production') && ! $request->isSecure()) {
            return response()->json(['message' => 'HTTPS is required.'], 426);
        }
        $token = $request->bearerToken();
        if (! is_string($token) || strlen($token) > 512 || ! hash_equals($hash, hash('sha256', $token))) {
            return response()->json(['message' => 'Invalid publishing credential.'], 401)
                ->header('WWW-Authenticate', 'Bearer')->header('Cache-Control', 'no-store');
        }
        if ((int) $request->header('Content-Length', 0) > config('publishing.max_request_bytes')
            || strlen($request->getContent()) > config('publishing.max_request_bytes')) {
            return response()->json(['message' => 'Publishing request is too large.'], 413);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
