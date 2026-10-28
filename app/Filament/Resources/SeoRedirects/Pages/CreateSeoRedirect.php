<?php

namespace App\Filament\Resources\SeoRedirects\Pages;

use App\Filament\Resources\SeoRedirects\SeoRedirectResource;
use App\Support\SeoRedirects;
use Filament\Resources\Pages\CreateRecord;

class CreateSeoRedirect extends CreateRecord
{
    protected static string $resource = SeoRedirectResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return SeoRedirectResource::validateForm($data);
    }

    protected function afterFill(): void
    {
        $source = request()->query('source');
        if ($path = app(SeoRedirects::class)->path($source)) {
            $this->data['source_path'] = $path;
        }
    }
}
