<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Filament\Support\ContentFields;
use App\Filament\Support\MediaFields;
use App\Models\Project;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Project details')->schema([
                ContentFields::title(), ContentFields::slug('projects'), ContentFields::category('project'),
                Textarea::make('excerpt')->required()->maxLength(1000)->rows(3)->columnSpanFull()->live(onBlur: true),
                ContentFields::body(), TagsInput::make('tools')->nestedRecursiveRules(['string', 'max:80'])->columnSpanFull(),
                TextInput::make('external_url')->label('Live website URL')->url()->regex('/^https?:\/\//i')->maxLength(2048),
                TextInput::make('repository_url')->url()->regex('/^https?:\/\//i')->maxLength(2048),
                TextInput::make('video_url')->label('Video demonstration URL')->url()->regex(Project::VIDEO_URL_PATTERN)->maxLength(2048)
                    ->required(fn (Get $get): bool => (bool) $get('video_schema_enabled'))
                    ->helperText('YouTube watch or youtu.be URL. Use a verified project demonstration; leave blank when the link needs review.'),
            ])->columns(2)->columnSpanFull(),
            Section::make('Project images')->schema([
                ContentFields::image('cover_image', 'projects'),
                ContentFields::image('gallery', 'projects/gallery')->multiple()->reorderable()->maxFiles(12)->live(),
                ...MediaFields::cover(), MediaFields::gallery(),
            ])->columns(2)->columnSpanFull(),
            MediaFields::video(), MediaFields::body(), ContentFields::publishing(featured: true, demo: true), ContentFields::seo('projects'),
            Section::make('Catalogue source & review')->description('Private source notes. Confirm your delivered work, technology and screenshots before publishing. Link checks do not verify every feature or project result.')->schema([
                TextInput::make('catalogue_key')->label('Import reference')->disabled()->dehydrated(false),
                TextInput::make('catalogue_metadata.status')->label('Mapping status at import')->disabled()->dehydrated(false),
                TextInput::make('catalogue_metadata.source_title')->label('Original project title')->disabled()->dehydrated(false)->columnSpanFull(),
                TextInput::make('catalogue_metadata.original_url')->label('Original evidence URL')->disabled()->dehydrated(false)->columnSpanFull(),
                TextInput::make('catalogue_metadata.candidate_url')->label('Candidate URL requiring review')->disabled()->dehydrated(false)->columnSpanFull(),
                Textarea::make('catalogue_metadata.notes')->label('Review notes')->rows(5)->disabled()->dehydrated(false)->columnSpanFull(),
                Textarea::make('catalogue_metadata.source_details')->label('Original document specifications (unverified)')->rows(7)->disabled()->dehydrated(false)->columnSpanFull()
                    ->visible(fn (?Project $record): bool => filled($record?->catalogue_metadata['source_details'] ?? null)),
            ])->columns(2)->columnSpanFull()->visible(fn (?Project $record): bool => filled($record?->catalogue_key)),
        ]);
    }
}
