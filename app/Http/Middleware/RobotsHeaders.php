<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RobotsHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! config('portfolio.indexable') || $request->is('admin', 'admin/*', 'livewire/*', 'inquiries', 'up')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        } elseif ($robots = $request->attributes->get('portfolio.seo_robots')) {
            $response->headers->set('X-Robots-Tag', $robots);
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
