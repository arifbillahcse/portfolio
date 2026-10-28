<?php

namespace App\Filament\Support;

use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Rules\CanonicalUrl;
use App\Support\PublicContent;
use App\Support\PublicSeo;
use App\Support\SeoMetadata;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Illuminate\Database\Eloquent\Model;

class SeoFields
{
    public static function section(string $type, ?string $page = null): Section
    {
        return Section::make('Search appearance')->description('Blank fields use existing content and defaults. Keywords are private editorial notes, not a ranking guarantee.')->schema([
            TextInput::make('seo_title')->label('SEO title')->rule('string')->maxLength(255)->live(onBlur: true)
                ->helperText('The title template is applied after this text. Check the final title in the preview.'),
            Textarea::make('seo_description')->label('Meta description')->rule('string')->maxLength(320)->rows(3)->live(onBlur: true),
            TextInput::make('seo_focus_keyword')->label('Focus keyword / topic')->rule('string')->maxLength(255),
            TagsInput::make('seo_related_keywords')->label('Related keywords / topics')->rules(['array', 'max:15'])
                ->nestedRecursiveRules(['string', 'max:80']),
            TextInput::make('seo_canonical_url')->label('Canonical URL')->maxLength(2048)->rule(new CanonicalUrl)->live(onBlur: true)
                ->helperText('Normally leave blank. A canonical is a preferred URL, not a redirect. Pagination keeps its own URL.'),
            Select::make('seo_robots')->label('Search visibility')->options([
                'index_follow' => 'Index, follow', 'index_nofollow' => 'Index, nofollow',
                'noindex_follow' => 'Noindex, follow', 'noindex_nofollow' => 'Noindex, nofollow',
            ])->rule('string')->in(array_keys(SeoMetadata::ROBOTS))->placeholder('Automatic')->live()
                ->helperText('Global indexing restrictions, private previews, sample content and search filters take priority.'),
            TextInput::make('seo_social_title')->label('Social share title')->rule('string')->maxLength(255)->live(onBlur: true),
            Textarea::make('seo_social_description')->label('Social share description')->rule('string')->maxLength(320)->rows(3)->live(onBlur: true),
            ContentFields::image('seo_social_image', 'profile/seo')->label('Social share image'),
            TextInput::make('seo_social_image_alt')->label('Social image description')->rule('string')->maxLength(255)->live(onBlur: true),
            View::make('filament.seo-preview')->viewData(fn (Get $get, ?Model $record = null): array => [
                'preview' => self::preview($get, $type, $page, $record),
            ])->columnSpanFull(),
        ])->columns(2)->columnSpanFull()->collapsible();
    }

    /** @return array<string, mixed> */
    private static function preview(Get $get, string $type, ?string $page, ?Model $record): array
    {
        $site = SiteSetting::query()->where('key', 'main')->first() ?? new SiteSetting(['brand_name' => 'Arif Portfolio']);
        $profile = Profile::query()->where('key', 'main')->first() ?? new Profile(['name' => 'Arif']);
        if ($page && is_array($templates = $get('data.seo_title_templates', isAbsolute: true))) {
            $site->seo_title_templates = $templates;
        }
        $attributes = [];
        foreach (SeoMetadata::FIELDS as $field) {
            $attributes[$field] = $get($field);
        }
        $image = $attributes['seo_social_image'] ?? null;
        if (is_array($image)) {
            $attributes['seo_social_image'] = collect($image)->first(fn (mixed $path): bool => is_string($path));
        }
        $defaults = $page ? SeoMetadata::pageDefaults($page, $profile, $site) : [
            'title' => is_string($get('title')) ? $get('title') : 'Your title',
            'description' => is_string($summary = $get($type === 'services' ? 'summary' : 'excerpt')) ? $summary : '',
        ];
        $canonical = $page ? PublicSeo::url($page)
            : PublicSeo::url($type.'.show', ['slug' => $get('slug') ?: 'your-page']);
        $cover = $get('cover_image');
        if (is_array($cover)) {
            $cover = collect($cover)->first(fn (mixed $path): bool => is_string($path));
        }
        $fallbackImage = is_string($cover) ? PublicContent::image($cover) : null;
        $preview = SeoMetadata::resolve($attributes, $site, $profile, $page ? ($page === 'home' ? 'home' : 'pages') : $type,
            $defaults['title'], $defaults['description'], $canonical,
            $fallbackImage ? SeoMetadata::absoluteImage($fallbackImage) : rtrim(config('app.url'), '/').'/assets/images/arif-formal.png');
        $warnings = [];
        if (! config('portfolio.indexable')) {
            $warnings[] = 'Global search indexing is disabled on this installation. Page settings cannot enable it.';
        }
        if (mb_strlen($preview['title']) > 65) {
            $warnings[] = 'The title may be shortened in search results. Review the preview; character count is only a guide.';
        }
        if (mb_strlen($preview['description']) > 170) {
            $warnings[] = 'The description may be shortened in search results.';
        }
        if (blank($preview['description'])) {
            $warnings[] = 'Add a description or a useful content summary.';
        }
        $class = match ($type) {
            'blog' => Post::class, 'services' => Service::class, 'projects' => Project::class, default => null,
        };
        if (! $page && $class) {
            foreach (['seo_title' => 'title', 'seo_description' => 'description'] as $field => $label) {
                $value = $attributes[$field] ?? null;
                if (is_string($value) && filled($value) && $class::query()->where($field, $value)
                    ->when($record?->exists, fn ($query) => $query->whereKeyNot($record->getKey()))->exists()) {
                    $warnings[] = 'Another item of this content type uses the same SEO '.$label.'.';
                }
            }
        }

        if ($page) {
            foreach (SeoMetadata::PAGES as $other => $label) {
                if ($other === $page) {
                    continue;
                }
                $otherState = $get('data.page_seo.'.$other, isAbsolute: true) ?? [];
                $otherDefaults = SeoMetadata::pageDefaults($other, $profile, $site);
                $otherPreview = SeoMetadata::resolve($otherState, $site, $profile, $other === 'home' ? 'home' : 'pages',
                    $otherDefaults['title'], $otherDefaults['description'], PublicSeo::url($other));
                if ($preview['title'] === $otherPreview['title']) {
                    $warnings[] = 'Another main page uses the same SEO title: '.$label.'.';
                }
                if (filled($preview['description']) && $preview['description'] === $otherPreview['description']) {
                    $warnings[] = 'Another main page uses the same meta description: '.$label.'.';
                }
            }
        }

        return [...$preview, 'warnings' => $warnings, 'title_length' => mb_strlen($preview['title']),
            'description_length' => mb_strlen($preview['description'])];
    }
}
