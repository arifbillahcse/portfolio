<?php

namespace App\Filament\Resources\Profiles\Schemas;

use App\Filament\Support\ContentFields;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Introduction')->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('headline')->required()->maxLength(255),
                Textarea::make('intro')->required()->maxLength(2000)->columnSpanFull(),
                ContentFields::body('biography'),
                ContentFields::image('portrait_path', 'profile')->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            Section::make('Homepage introduction')->schema([
                TextInput::make('home_heading')->maxLength(255),
                Textarea::make('home_intro')->maxLength(2000),
            ])->columnSpanFull(),
            Section::make('Businesses & client work')->schema([
                Repeater::make('businesses')->schema([
                    TextInput::make('name')->required()->maxLength(150),
                    TextInput::make('focus')->required()->maxLength(150),
                    Textarea::make('description')->required()->maxLength(1500)->columnSpanFull(),
                ])->columns(2)->defaultItems(0)->maxItems(10)->collapsible()->columnSpanFull(),
                Textarea::make('support_story')->maxLength(5000)->rows(5)->columnSpanFull(),
            ])->columnSpanFull(),
            Section::make('Career journey')->schema([
                Repeater::make('journey')->schema([
                    TextInput::make('label')->required()->maxLength(80), TextInput::make('title')->required()->maxLength(150),
                    Textarea::make('description')->required()->maxLength(1500)->columnSpanFull(),
                ])->columns(2)->defaultItems(0)->maxItems(12)->collapsible()->columnSpanFull(),
            ])->columnSpanFull(),
            Section::make('Skills & highlights')->schema([
                Repeater::make('skill_groups')->schema([
                    TextInput::make('title')->required()->maxLength(120),
                    TagsInput::make('skills')->required()->nestedRecursiveRules(['string', 'max:100']),
                ])->defaultItems(0)->maxItems(12)->collapsible()->columnSpanFull(),
                Repeater::make('stats')->schema([
                    TextInput::make('value')->required()->maxLength(50), TextInput::make('label')->required()->maxLength(100),
                ])->columns(2)->defaultItems(0)->maxItems(8)->columnSpanFull(),
            ])->columnSpanFull(),
            Section::make('Vision')->schema([
                Textarea::make('vision_quote')->maxLength(1000), Textarea::make('vision_note')->maxLength(3000),
            ])->columns(2)->columnSpanFull(),
        ]);
    }
}
