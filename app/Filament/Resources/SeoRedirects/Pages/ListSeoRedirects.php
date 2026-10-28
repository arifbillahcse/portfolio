<?php

namespace App\Filament\Resources\SeoRedirects\Pages;

use App\Filament\Resources\SeoRedirects\SeoRedirectResource;
use App\Models\SeoRedirect;
use App\Support\SeoRedirects;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use JsonException;

class ListSeoRedirects extends ListRecords
{
    protected static string $resource = SeoRedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('importJson')->label('Import JSON')->schema([
                Textarea::make('json')->required()->maxLength(1000000)->rows(12)->helperText('Paste a JSON list with source_path, destination_path, status_code, enabled and optional notes. Existing sources are preserved.'),
            ])->action(function (array $data): void {
                Gate::authorize('create', SeoRedirect::class);
                try {
                    $entries = json_decode($data['json'], true, 32, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw ValidationException::withMessages(['json' => 'The redirect catalogue is not valid JSON.']);
                }
                $result = app(SeoRedirects::class)->import($entries);
                Notification::make()->title('Imported '.$result['created'].' redirects; preserved '.$result['skipped'].' existing sources.')->success()->send();
            }),
            Action::make('exportJson')->label('Export JSON')->action(function () {
                Gate::authorize('viewAny', SeoRedirect::class);

                return response()->streamDownload(function (): void {
                    echo json_encode(SeoRedirect::query()->orderBy('id')->get(['source_path', 'destination_path', 'status_code', 'enabled', 'notes'])->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                }, 'seo-redirects.json', ['Content-Type' => 'application/json']);
            }),
        ];
    }
}
