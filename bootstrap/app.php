<?php

use App\Http\Middleware\RobotsHeaders;
use App\Http\Middleware\SeoRedirectMiddleware;
use App\Models\Post;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (): string => route('filament.admin.auth.login'));
        $middleware->append(RobotsHeaders::class);
        $middleware->append(SeoRedirectMiddleware::class);
        $middleware->trustHosts(at: function (): array {
            $host = parse_url(config('app.url'), PHP_URL_HOST) ?? '';
            $hosts = [$host];
            if (config('seo.canonical_origin_redirects')) {
                $hosts[] = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.'.$host;
            }

            return array_map(fn (string $host): string => '^'.preg_quote($host, '/').'$', $hosts);
        }, subdomains: false);
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $exception, Request $request): ?RedirectResponse {
            if (! $request->isMethod('GET') || $request->expectsJson() || $request->is('admin', 'admin/*', 'livewire/*', 'api/*')) {
                return null;
            }
            $path = '/'.trim(rawurldecode($request->getPathInfo()), '/');
            $post = Post::query()->published()->where('wordpress_path', $path)->first();
            if ($post && $path !== '/blog/'.$post->slug) {
                return redirect()->route('blog.show', ['slug' => $post->slug], 301);
            }

            return null;
        });
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
