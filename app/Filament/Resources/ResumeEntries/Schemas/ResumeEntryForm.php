<?php

namespace App\Filament\Resources\ResumeEntries\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ResumeEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Resume entry')->schema([
                Select::make('type')->options(['education' => 'Education', 'experience' => 'Experience', 'learning' => 'Learning', 'extracurricular' => 'Extracurricular'])->in(['education', 'experience', 'learning', 'extracurricular'])->required(),
                TextInput::make('title')->required()->maxLength(255),
                TextInput::make('organization')->maxLength(255), TextInput::make('location')->maxLength(255),
                TextInput::make('start_label')->label('Started')->maxLength(50)->helperText('For example: 2020 or March 2020.'),
                TextInput::make('end_label')->label('Ended')->maxLength(50),
                Toggle::make('is_current')->label('Ongoing')->default(false),
                TextInput::make('sort_order')->numeric()->integer()->minValue(0)->maxValue(1000000)->required()->default(0),
                Textarea::make('description')->maxLength(5000)->columnSpanFull(),
                Toggle::make('is_published')->label('Published')->default(false),
            ])->columns(2)->columnSpanFull(),
        ]);
    }
}
