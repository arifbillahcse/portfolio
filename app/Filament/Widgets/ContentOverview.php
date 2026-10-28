<?php

namespace App\Filament\Widgets;

use App\Models\Inquiry;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContentOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('New inquiries', Inquiry::query()->where('status', 'new')->count())->description('Client inbox')->url(route('filament.admin.resources.inquiries.index')),
            Stat::make('Projects', Project::query()->count())->description(Project::query()->published()->count().' published')->url(route('filament.admin.resources.projects.index')),
            Stat::make('Blog posts', Post::query()->count())->description(Post::query()->published()->count().' published')->url(route('filament.admin.resources.posts.index')),
            Stat::make('Services', Service::query()->count())->description(Service::query()->published()->count().' published')->url(route('filament.admin.resources.services.index')),
        ];
    }
}
