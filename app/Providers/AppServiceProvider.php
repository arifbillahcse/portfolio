<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Inquiry;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ResumeEntry;
use App\Models\SeoRedirect;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Policies\ContentPolicy;
use App\Policies\SingletonContentPolicy;
use App\Support\MediaSeo;
use App\Support\ResponsiveImages;
use App\Support\SeoRedirects;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(MediaSeo::class);
        $this->app->scoped(ResponsiveImages::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        TrustProxies::at(config('portfolio.trusted_proxies'));

        Event::listen(DiagnosingHealth::class, function (): void {
            DB::connection()->select('select 1');
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        foreach ([Category::class, Project::class, Post::class, Service::class, ResumeEntry::class, SeoRedirect::class] as $model) {
            Gate::policy($model, ContentPolicy::class);
        }

        foreach ([Post::class, Project::class, Service::class] as $model) {
            $model::updated(fn (Model $record) => app(SeoRedirects::class)->suggest($record));
        }

        foreach ([Profile::class, SiteSetting::class, Inquiry::class] as $model) {
            Gate::policy($model, SingletonContentPolicy::class);
        }

        RateLimiter::for('inquiries', fn (Request $request): array => [
            Limit::perMinute(5)->by('minute:'.hash('sha256', $request->ip() ?? 'unknown')),
            Limit::perHour(20)->by('hour:'.hash('sha256', $request->ip() ?? 'unknown')),
        ]);
    }
}
