<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Support\PublicSeo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    private const PAGE_SIZE = 1000;

    public function robots(): Response
    {
        $body = config('portfolio.indexable')
            ? "User-agent: *\nDisallow: /admin\nDisallow: /inquiries\nSitemap: ".PublicSeo::url('sitemap')."\n"
            : "User-agent: *\nDisallow: /\n";

        return response($body)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function index(): Response
    {
        $locations = [];
        if (config('portfolio.indexable')) {
            $locations[] = PublicSeo::url('sitemap.pages');
            foreach (['projects', 'blog', 'services'] as $section) {
                $pages = (int) ceil($this->query($section)->count() / self::PAGE_SIZE);
                for ($page = 1; $page <= $pages; $page++) {
                    $locations[] = PublicSeo::url('sitemap.section', ['section' => $section, 'page' => $page]);
                }
            }
        }

        return response()->view('seo.sitemap-index', compact('locations'))->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function pages(): Response
    {
        $entries = [];
        if (config('portfolio.indexable')) {
            foreach (['home', 'about', 'services', 'projects', 'blog', 'contact'] as $page) {
                if (in_array($page, ['projects', 'blog'], true) && ! $this->query($page)->exists()) {
                    continue;
                }
                $entries[] = ['url' => PublicSeo::url($page), 'modified' => null];
            }
        }

        return $this->xml($entries);
    }

    public function section(string $section, string $page): Response
    {
        abort_unless(config('portfolio.indexable'), 404);
        $number = filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        abort_unless($number !== false, 404);
        $records = $this->query($section)->orderBy('id')->offset(($number - 1) * self::PAGE_SIZE)->limit(self::PAGE_SIZE)->get();
        abort_if($records->isEmpty(), 404);
        $entries = $records->map(fn ($record): array => [
            'url' => PublicSeo::url($section.'.show', ['slug' => $record->slug]),
            'modified' => $record->updated_at->toIso8601String(),
        ])->all();

        return $this->xml($entries);
    }

    private function query(string $section): Builder
    {
        return match ($section) {
            'projects' => Project::query()->published()->where('is_demo', false),
            'blog' => Post::query()->published()->where('is_demo', false),
            'services' => Service::query()->published(),
            default => abort(404),
        };
    }

    private function xml(array $entries): Response
    {
        return response()->view('seo.sitemap', compact('entries'))->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
