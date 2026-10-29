<?php

namespace App\Filament\Pages;

use App\Support\GrowthReport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class GrowthDashboard extends Page
{
    protected static ?string $title = 'Growth dashboard';

    protected static ?string $slug = 'growth-dashboard';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected string $view = 'filament.pages.growth-dashboard';

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

    protected function getHeaderActions(): array
    {
        return [Action::make('refresh')->label('Refresh report')->action(fn () => $this->refresh())];
    }

    public function refresh(): void
    {
        abort_unless(static::canAccess(), 403);
        $growth = app(GrowthReport::class);
        $growth->save($growth->build());
        Notification::make()->title('Growth report refreshed')->success()->send();
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        return ['report' => app(GrowthReport::class)->read()];
    }
}
