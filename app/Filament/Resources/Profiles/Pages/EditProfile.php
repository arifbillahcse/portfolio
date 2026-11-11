<?php

namespace App\Filament\Resources\Profiles\Pages;

use App\Filament\Pages\SeoSettings;
use App\Filament\Resources\Profiles\ProfileResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditProfile extends EditRecord
{
    protected static string $resource = ProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pageSeo')->label('Page SEO')->url(SeoSettings::getUrl()),
        ];
    }
}
