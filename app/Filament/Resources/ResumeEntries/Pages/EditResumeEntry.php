<?php

namespace App\Filament\Resources\ResumeEntries\Pages;

use App\Filament\Resources\ResumeEntries\ResumeEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditResumeEntry extends EditRecord
{
    protected static string $resource = ResumeEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
