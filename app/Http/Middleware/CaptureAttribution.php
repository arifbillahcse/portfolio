<?php

namespace App\Http\Middleware;

use App\Support\AnalyticsTracking;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureAttribution
{
    public function __construct(private AnalyticsTracking $tracking) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tracking->remember($request);

        return $next($request);
    }
}
