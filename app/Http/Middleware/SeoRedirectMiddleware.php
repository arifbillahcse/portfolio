<?php

namespace App\Http\Middleware;

use App\Models\SeoRedirect;
use App\Support\SeoRedirects;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SeoRedirectMiddleware
{
    public function __construct(private SeoRedirects $redirects) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }
        $origin = rtrim(config('app.url'), '/');
        if (config('seo.canonical_origin_redirects') && $request->getSchemeAndHttpHost() !== $origin) {
            return redirect($origin.$request->getRequestUri(), 301);
        }
        if (! $request->expectsJson() && ($path = $this->redirects->requestPath($request))) {
            $rule = SeoRedirect::query()->where('source_path', $path)->where('enabled', true)->first();
            if ($rule && ($target = $this->redirects->destination($rule->destination_path, [$path]))) {
                return redirect($origin.$target, $rule->status_code);
            }
        }
        $response = $next($request);
        if ($response->getStatusCode() === 404 && $request->isMethod('GET') && ! $request->expectsJson()) {
            $this->redirects->record404($request);
        }

        return $response;
    }
}
