<?php

namespace App\Filament\Pages;

use App\Filament\Support\SeoFields;
use App\Models\SiteSetting;
use App\Rules\SeoTitleTemplate;
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

class SeoSettings extends Page
{
    use RestrictsFileUploadsToSchemaComponents;

    protected static ?string $title = 'SEO settings';

    protected static ?string $slug = 'seo-settings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.seo-settings';

    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
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
            $tabs[] = Tab::make($label)->schema([
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
            Section::make('Title templates')->description('Available tokens: {title}, {brand}, {name}. Existing titles are preserved by the defaults. Pagination adds its page number automatically.')
                ->schema($templates)->columns(2)->collapsible(),
            Tabs::make('Main page SEO')->tabs($tabs)->columnSpanFull(),
        ])->statePath('data');
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
