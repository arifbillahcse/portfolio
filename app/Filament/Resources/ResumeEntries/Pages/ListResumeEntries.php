<?php

namespace App\Filament\Resources\ResumeEntries\Pages;

use App\Filament\Resources\ResumeEntries\ResumeEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResumeEntries extends ListRecords
{
    protected static string $resource = ResumeEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
