<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

class SeoNotFound extends Page
{
    use WithPagination;

    protected static ?string $title = '404 monitor';

    protected static ?string $slug = 'seo-not-found';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected string $view = 'filament.pages.seo-not-found';

    public string $search = '';

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
        $this->resetPage();
    }

    public function remove(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        DB::table('seo_not_found')->where('id', $id)->delete();
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['search' => ['string', 'max:200']]);

        return ['entries' => DB::table('seo_not_found')->when($this->search !== '', fn ($query) => $query->where('path', 'like', '%'.$this->search.'%'))->orderByDesc('last_seen_at')->paginate(25)];
    }
}
