<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Support\ContentFields;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Category')->schema([
                ContentFields::title('name'),
                Select::make('type')->options(['project' => 'Projects', 'post' => 'Blog posts'])->in(['project', 'post'])->default('project')->required()->live()->disabledOn('edit'),
                TextInput::make('slug')->required()->maxLength(255)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique(table: 'categories', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('type', $get('type'))),
                TextInput::make('sort_order')->numeric()->integer()->minValue(0)->maxValue(1000000)->default(0)->required(),
                Textarea::make('description')->maxLength(1000)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }
}
