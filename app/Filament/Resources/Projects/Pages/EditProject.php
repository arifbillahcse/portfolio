<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')->label('Preview saved project')
                ->url(fn (): string => route('projects.preview', ['project' => $this->getRecord()]))->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
