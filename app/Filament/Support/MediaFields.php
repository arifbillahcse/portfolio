<?php

namespace App\Filament\Support;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class MediaFields
{
    /** @return array<int, TextInput> */
    public static function cover(): array
    {
        return [
            TextInput::make('cover_image_alt')->label('Cover image alt text')->rule('string')->maxLength(255)
                ->helperText('Describe what this image shows. Blank uses the content title. Avoid lists of keywords.'),
            TextInput::make('cover_image_caption')->label('Cover caption')->rule('string')->maxLength(500),
        ];
    }

    public static function body(): Section
    {
        return Section::make('Article and content images')->description('Use the Markdown editor to write image alt text, or override a specific image here. The original Markdown stays unchanged.')->schema([
            Repeater::make('body_image_metadata')->label('Inline image descriptions')->defaultItems(0)->maxItems(100)->schema([
                TextInput::make('src')->label('Exact image URL used in the content')->required()->rule('string')->maxLength(2048)
                    ->regex('~\A(?:https?://|/(?:storage|assets)/)[^\s<>]+\z~i'),
                TextInput::make('alt')->label('Image alt text')->rule('string')->maxLength(255)
                    ->live(onBlur: true)
                    ->required(fn (Get $get): bool => ! $get('decorative')),
                Toggle::make('decorative')->label('Decorative image (empty alt text)')->default(false)->live(),
            ])->columns(2)->columnSpanFull(),
        ])->columnSpanFull()->collapsible()->collapsed();
    }

    public static function gallery(): Repeater
    {
        return Repeater::make('gallery_metadata')->label('Gallery descriptions')->defaultItems(0)->maxItems(12)->schema([
            Select::make('src')->label('Gallery image')->required()->options(function (Get $get): array {
                $paths = array_filter($get('../../gallery') ?? [], 'is_string');

                return array_combine($paths, array_map('basename', $paths));
            })->disableOptionsWhenSelectedInSiblingRepeaterItems(),
            TextInput::make('alt')->label('Screenshot alt text')->required()->rule('string')->maxLength(255),
            TextInput::make('caption')->rule('string')->maxLength(500),
        ])->columns(2)->columnSpanFull()->helperText('Choose an uploaded gallery image. Descriptions follow the image when you reorder the gallery.');
    }

    public static function video(): Section
    {
        $enabled = fn (Get $get): bool => (bool) $get('video_schema_enabled');

        return Section::make('Video search metadata')->description('Use the actual details of the embedded demonstration. Missing or invalid metadata suppresses VideoObject; the video itself still works.')->schema([
            Toggle::make('video_schema_enabled')->label('Enable verified video structured data')->default(false)->live(),
            TextInput::make('video_name')->label('Actual video title')->rule('string')->maxLength(255)->required($enabled),
            Textarea::make('video_description')->rule('string')->maxLength(2000)->required($enabled)->columnSpanFull(),
            ContentFields::image('video_thumbnail', 'projects/video')->label('Actual video thumbnail')->required($enabled),
            DateTimePicker::make('video_uploaded_at')->label('Original video upload date and time')->seconds(false)->maxDate(now())->required($enabled)
                ->helperText('Use the original video date, not the date this project was added. Stored in the application timezone.'),
            TextInput::make('video_duration_seconds')->label('Duration in seconds (optional)')->integer()->minValue(1)->maxValue(604800),
        ])->columns(2)->columnSpanFull()->collapsible()->collapsed();
    }
}
