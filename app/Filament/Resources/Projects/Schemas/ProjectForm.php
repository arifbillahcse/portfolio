<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Filament\Support\ContentFields;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Project details')->schema([
                ContentFields::title(), ContentFields::slug('projects'), ContentFields::category('project'),
                Textarea::make('excerpt')->required()->maxLength(1000)->rows(3)->columnSpanFull(),
                ContentFields::body(), TagsInput::make('tools')->nestedRecursiveRules(['string', 'max:80'])->columnSpanFull(),
                TextInput::make('external_url')->label('Live website URL')->url()->regex('/^https?:\/\//i')->maxLength(2048),
                TextInput::make('repository_url')->url()->regex('/^https?:\/\//i')->maxLength(2048),
            ])->columns(2)->columnSpanFull(),
            Section::make('Project images')->schema([
                ContentFields::image('cover_image', 'projects'),
                ContentFields::image('gallery', 'projects/gallery')->multiple()->reorderable()->maxFiles(12),
            ])->columns(2)->columnSpanFull(),
            ContentFields::publishing(featured: true, demo: true), ContentFields::seo(),
        ]);
    }
}
