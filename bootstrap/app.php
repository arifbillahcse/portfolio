<?php

use App\Http\Middleware\RobotsHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RobotsHeaders::class);
        $middleware->trustHosts(at: fn (): array => ['^'.preg_quote(parse_url(config('app.url'), PHP_URL_HOST) ?? '', '/').'$'], subdomains: false);
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

$app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
    $publicPath = $app['config']->get('portfolio.public_path');
    if (is_string($publicPath) && $publicPath !== '') {
        $app->usePublicPath($publicPath);
        $links = $app['config']->get('filesystems.links', []);
        unset($links[$app->basePath('public/storage')]);
        $links[$app->publicPath('storage')] = $app->storagePath('app/public');
        $app['config']->set('filesystems.links', $links);
    }
});

return $app;
