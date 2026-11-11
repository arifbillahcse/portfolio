<?php

namespace App\Filament\Pages;

use App\Filament\Support\SeoFields;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Rules\SeoTitleTemplate;
use App\Support\ContentAnalysis;
use App\Support\PublicSeo;
use App\Support\SeoMetadata;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Livewire\Attributes\Url;

class SeoSettings extends Page
{
    use RestrictsFileUploadsToSchemaComponents;

    protected static ?string $title = 'SEO settings';

    protected static ?string $navigationLabel = 'Page SEO';

    protected static ?string $slug = 'seo-settings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.seo-settings';

    public ?array $data = [];

    #[Url(as: 'page', except: 'home')]
    public string $activePage = 'home';

    public static function getNavigationGroup(): ?string
    {
        return 'SEO';
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->updatedActivePage();
        $site = SiteSetting::query()->where('key', 'main')->first();
        $this->form->fill([
            'page_seo' => $site?->page_seo ?? [],
            'seo_title_templates' => array_replace(SeoMetadata::TEMPLATES, $site?->seo_title_templates ?? []),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];
        foreach (SeoMetadata::PAGES as $key => $label) {
            $tabs[$key] = Tab::make($label)->schema([
                SeoFields::section('pages', $key)->statePath('page_seo.'.$key),
            ]);
        }
        $templates = [];
        foreach (SeoMetadata::TEMPLATES as $key => $default) {
            $templates[] = TextInput::make('seo_title_templates.'.$key)
                ->label(match ($key) {
                    'home' => 'Home title template', 'pages' => 'Main page title template', 'blog' => 'Post title template',
                    'services' => 'Service title template', default => 'Project title template',
                })->required()->maxLength(160)->rule(new SeoTitleTemplate)->live(onBlur: true);
        }

        return $schema->components([
            Tabs::make('Main page SEO')->tabs($tabs)->livewireProperty('activePage')->columnSpanFull(),
            Section::make('Title templates')->description('Available tokens: {title}, {brand}, {name}. Existing titles are preserved by the defaults. Pagination adds its page number automatically.')
                ->schema($templates)->columns(2)->collapsible()->collapsed(),
        ])->statePath('data');
    }

    public function updatedActivePage(): void
    {
        abort_unless(array_key_exists($this->activePage, SeoMetadata::PAGES), 404);
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $site = SiteSetting::query()->where('key', 'main')->first() ?? new SiteSetting(['brand_name' => 'Arif Portfolio']);
        $profile = Profile::query()->where('key', 'main')->first() ?? new Profile(['name' => 'Arif']);
        $site->seo_title_templates = $this->data['seo_title_templates'] ?? [];
        $originalContent = [
            'projects' => Project::query()->published()->where('is_demo', false)->exists(),
            'blog' => Post::query()->published()->where('is_demo', false)->exists(),
        ];
        $sitemapContent = [
            'projects' => SeoMetadata::indexableQuery(Project::query()->published()->where('is_demo', false), 'projects')->exists(),
            'blog' => SeoMetadata::indexableQuery(Post::query()->published()->where('is_demo', false), 'blog')->exists(),
        ];
        $pages = [];
        foreach (SeoMetadata::PAGES as $key => $label) {
            $attributes = $this->data['page_seo'][$key] ?? [];
            $defaults = SeoMetadata::pageDefaults($key, $profile, $site);
            $url = PublicSeo::url($key);
            $metadata = SeoMetadata::resolve($attributes, $site, $profile, $key === 'home' ? 'home' : 'pages',
                $defaults['title'], $defaults['description'], $url);
            $analysis = ContentAnalysis::analyze($attributes, [...$metadata, 'self_url' => $url], false);
            $robots = $metadata['robots'] ?? 'index, follow';
            $reason = 'Page settings allow indexing; search engines decide whether to index it.';
            if (! config('portfolio.indexable')) {
                $robots = 'noindex, nofollow';
                $reason = 'Global indexing is disabled.';
            } elseif (! ($originalContent[$key] ?? true)) {
                $robots = 'noindex, follow';
                $reason = 'Publish original content to enable indexing of this listing.';
            } elseif (str_contains($robots, 'noindex')) {
                $reason = 'This page is set to noindex in Advanced.';
            }
            $pages[$key] = [
                'label' => $label, 'url' => $url, 'title' => $metadata['title'], 'description' => $metadata['description'],
                'custom_title' => filled($attributes['seo_title'] ?? null),
                'custom_description' => filled($attributes['seo_description'] ?? null),
                'robots' => $robots, 'visibility_reason' => $reason,
                'sitemap' => config('portfolio.indexable') && ($sitemapContent[$key] ?? true)
                    && SeoMetadata::sitemapAllowed($attributes, $url),
                'canonical_elsewhere' => SeoMetadata::validCanonical($attributes['seo_canonical_url'] ?? null)
                    && rtrim($metadata['canonical'], '/') !== rtrim($url, '/'),
                'reviews' => array_values(array_map(fn (array $check): string => $check['message'],
                    array_filter($analysis['checks'], fn (array $check): bool => $check['status'] === 'review' || $check['code'] === 'metadata_length'))),
            ];
        }
        foreach ($pages as $key => $page) {
            foreach (['title' => 'SEO title', 'description' => 'meta description'] as $field => $label) {
                if (filled($page[$field]) && count(array_filter($pages, fn (array $other): bool => $other[$field] === $page[$field])) > 1) {
                    $pages[$key]['reviews'][] = 'Another main page uses the same '.$label.'. Write text specific to this page.';
                }
            }
        }

        return ['pages' => $pages,
            'reviewCount' => count(array_filter($pages, fn (array $page): bool => $page['reviews'] !== [])),
            'noindexCount' => count(array_filter($pages, fn (array $page): bool => str_contains($page['robots'], 'noindex'))),
            'activePageLabel' => SeoMetadata::PAGES[$this->activePage] ?? 'Page',
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->form->getState();
        $pages = [];
        foreach (array_keys(SeoMetadata::PAGES) as $page) {
            $pages[$page] = Arr::only($state['page_seo'][$page] ?? [], SeoMetadata::FIELDS);
        }
        $site = SiteSetting::query()->firstOrNew(['key' => 'main']);
        if (! $site->exists) {
            $site->fill(['brand_name' => 'Arif Portfolio', 'contact_email' => 'emran.alarif8@gmail.com', 'whatsapp_number' => '+8801779440297']);
        }
        $site->fill(['page_seo' => $pages,
            'seo_title_templates' => Arr::only($state['seo_title_templates'], array_keys(SeoMetadata::TEMPLATES))])->save();
        Notification::make()->title('SEO settings saved')->success()->send();
    }
}
