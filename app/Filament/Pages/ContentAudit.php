<?php

namespace App\Filament\Pages;

use App\Support\SeoContentAudit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContentAudit extends Page
{
    use WithPagination;

    protected static ?string $title = 'Content audit';

    protected static ?string $slug = 'seo-content-audit';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected string $view = 'filament.pages.content-audit';

    public string $search = '';

    public string $kind = 'all';

    public string $status = 'all';

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
    }

    public function updatedSearch(): void
    {
        $this->validateOnly('search', ['search' => ['string', 'max:200']]);
        $this->resetPage();
    }

    public function updatedKind(): void
    {
        $this->validateOnly('kind', ['kind' => ['in:all,pages,blog,projects,services']]);
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->validateOnly('status', ['status' => ['in:all,review']]);
        $this->resetPage();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('download')->label('Download saved JSON')->action(fn (): StreamedResponse => $this->download())];
    }

    public function download(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $report = app(SeoContentAudit::class)->read();
        abort_unless($report, 404);

        return response()->streamDownload(function () use ($report): void {
            echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }, 'seo-content-audit.json', ['Content-Type' => 'application/json']);
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $report = app(SeoContentAudit::class)->read();
        $search = mb_strtolower(mb_substr($this->search, 0, 200));
        $rows = collect($report['entries'] ?? [])->filter(fn (array $row): bool => ($this->kind === 'all' || $row['kind'] === $this->kind)
            && ($this->status === 'all' || $row['review_count'] > 0)
            && ($search === '' || str_contains(mb_strtolower($row['title'].' '.$row['url'].' '.$row['topic']), $search)))->values();

        $page = filter_var($this->getPage(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]) ?: 1;

        return ['report' => $report, 'entries' => new LengthAwarePaginator($rows->forPage($page, 20)->values(), $rows->count(), 20, $page)];
    }

    /** @param array<string, mixed> $entry */
    public function editUrl(array $entry): ?string
    {
        abort_unless(static::canAccess(), 403);
        if (($entry['kind'] ?? null) === 'pages') {
            return route('filament.admin.pages.seo-settings');
        }
        if (! is_int($entry['id'] ?? null) || $entry['id'] < 1) {
            return null;
        }
        $resource = match ($entry['kind'] ?? null) {
            'blog' => 'posts', 'projects' => 'projects', 'services' => 'services', default => null,
        };

        return $resource ? route('filament.admin.resources.'.$resource.'.edit', ['record' => $entry['id']]) : null;
    }
}
