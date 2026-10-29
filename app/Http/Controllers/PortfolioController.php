<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ResumeEntry;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Support\PublicContent;
use App\Support\PublicSeo;
use App\Support\RenderedContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function home(Request $request): View|RedirectResponse
    {
        if ($request->query->has('p')) {
            abort_unless(is_scalar($request->query('p')) && ctype_digit((string) $request->query('p')), 404);
            $post = Post::query()->published()->where('wordpress_id', $request->query('p'))->first();
            if ($post) {
                return redirect()->route('blog.show', ['slug' => $post->slug], 301);
            }
            abort(404);
        }
        if ($request->query->has('page_id')) {
            abort_unless(is_scalar($request->query('page_id')), 404);
            $page = ['3730' => 'about', '3761' => 'services'][(string) $request->query('page_id')] ?? null;
            abort_unless($page, 404);

            return redirect()->route($page, [], 301);
        }

        return $this->page('home', [
            'projects' => Project::query()->published()->with('category')->orderByDesc('is_featured')->orderBy('sort_order')->orderByDesc('id')->limit(6)->get(),
        ]);
    }

    public function about(): View
    {
        return $this->page('about', [
            'resume' => ResumeEntry::query()->where('is_published', true)->orderBy('sort_order')->orderBy('id')->get()->groupBy('type'),
        ]);
    }

    public function services(): View
    {
        return $this->page('services', [
            'services' => Service::query()->published()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function projects(Request $request): View
    {
        $filters = $request->validate(['category' => ['nullable', 'string', 'max:255'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = Project::query()->published()->with('category');
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $query): Builder => $query->where('type', 'project')->where('slug', $filters['category']));
        }

        return $this->page('projects', [
            'projects' => $query->orderBy('sort_order')->orderByDesc('published_at')->orderByDesc('id')->paginate(9)->withQueryString(),
            'categories' => Category::query()->where('type', 'project')->whereHas('projects', fn (Builder $query): Builder => $query->published())->orderBy('sort_order')->orderBy('id')->get(),
            'category' => $filters['category'] ?? '',
            'hasOriginalProjects' => Project::query()->published()->where('is_demo', false)->exists(),
        ]);
    }

    public function blog(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:255'], 'page' => ['nullable', 'integer', 'min:1']]);
        $search = trim($filters['q'] ?? '');
        $query = Post::query()->published()->with('category');
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $query): Builder => $query->where('type', 'post')->where('slug', $filters['category']));
        }
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')->orWhere('excerpt', 'like', '%'.$search.'%');
            });
        }

        return $this->page('blog', [
            'posts' => $query->orderBy('sort_order')->orderByDesc('published_at')->orderByDesc('id')->paginate(9)->withQueryString(),
            'featured' => $search === '' && empty($filters['category']) && $request->integer('page', 1) === 1
                ? Post::query()->published()->with('category')->where('is_featured', true)->orderBy('sort_order')->orderByDesc('id')->first() : null,
            'categories' => Category::query()->where('type', 'post')->whereHas('posts', fn (Builder $query): Builder => $query->published())->orderBy('sort_order')->orderBy('id')->get(),
            'category' => $filters['category'] ?? '',
            'search' => $search,
            'hasOriginalPosts' => Post::query()->published()->where('is_demo', false)->exists(),
        ]);
    }

    public function project(string $slug): View
    {
        return $this->detail(Project::query()->published()->with('category')->where('slug', $slug)->firstOrFail(), 'projects');
    }

    public function post(string $slug): View
    {
        return $this->detail(Post::query()->published()->with('category')->where('slug', $slug)->firstOrFail(), 'blog');
    }

    public function previewProject(Project $project): Response
    {
        Gate::authorize('view', $project);

        return response($this->detail($project->load('category'), 'projects', isPreview: true))
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function service(string $slug): View
    {
        return $this->detail(Service::query()->published()->where('slug', $slug)->firstOrFail(), 'services');
    }

    public function contact(): View
    {
        return $this->page('contact');
    }

    private function detail(Project|Post|Service $entry, string $section, bool $isPreview = false): View
    {
        return $this->page('detail', [
            'entry' => $entry,
            'section' => $section,
            'isPreview' => $isPreview,
            'bodyHtml' => RenderedContent::body($entry, $isPreview),
        ]);
    }

    private function page(string $page, array $data = []): View
    {
        $profile = Profile::query()->where('key', 'main')->first() ?? new Profile(['name' => 'Arif', 'home_heading' => 'Hi, I’m Arif.', 'headline' => 'Development & cloud infrastructure']);
        $site = SiteSetting::query()->where('key', 'main')->first() ?? new SiteSetting([
            'brand_name' => 'Arif Portfolio', 'tagline' => 'Web development & cloud infrastructure.',
            'contact_email' => 'emran.alarif8@gmail.com', 'whatsapp_number' => '+8801779440297',
            'footer_text' => 'Arif. All rights reserved.',
        ]);

        $seo = PublicSeo::build($page, $profile, $site, $data);
        request()->attributes->set('portfolio.seo_robots', $seo['robots']);

        return view('pages.'.$page, array_merge([
            'profile' => $profile,
            'site' => $site,
            'seo' => $seo,
            'portrait' => PublicContent::image($profile->portrait_path) ?? asset('assets/images/arif-formal.png'),
            'portraitPath' => PublicContent::image($profile->portrait_path) ? $profile->portrait_path : 'assets/images/arif-formal.png',
            'contactLinks' => [
                'whatsapp' => 'https://wa.me/'.preg_replace('/\D/', '', $site->whatsapp_number ?? ''),
                'email' => 'mailto:'.$site->contact_email,
                'facebook' => PublicContent::link($site->facebook_url),
                'linkedin' => PublicContent::link($site->linkedin_url),
                'github' => PublicContent::link($site->github_url),
            ],
        ], $data));
    }
}
