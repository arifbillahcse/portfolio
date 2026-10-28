<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Filament\Support\ContentFields;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Article')->schema([
                ContentFields::title(), ContentFields::slug('posts'), ContentFields::category('post'),
                TextInput::make('reading_minutes')->numeric()->integer()->minValue(1)->maxValue(120)->required()->default(5),
                Textarea::make('excerpt')->required()->maxLength(1000)->rows(3)->columnSpanFull(),
                ContentFields::body()->required()
                    ->toolbarButtons(['bold', 'italic', 'link', 'heading', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'table', 'attachFiles'])
                    ->fileAttachmentsDisk('public')->fileAttachmentsDirectory('posts/content')
                    ->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->fileAttachmentsMaxSize(4096)
                    ->helperText('Use the attachment button to insert JPG, PNG or WebP images into the article. Maximum 4 MB per image.'),
                ContentFields::image('cover_image', 'posts')->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            ContentFields::publishing(featured: true, demo: true), ContentFields::seo(),
        ]);
    }
}
