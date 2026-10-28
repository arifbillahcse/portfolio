<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Filament\Support\ContentFields;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Service')->schema([
                ContentFields::title(), ContentFields::slug('services'),
                Select::make('group')->options(['development' => '01 · Build your platform', 'integrations' => '02 · Connect & automate', 'infrastructure' => '03 · Deploy', 'care' => '04 · Secure & maintain'])->in(['development', 'integrations', 'infrastructure', 'care'])->required()->default('development'),
                Select::make('icon')->options(['code' => 'Code', 'store' => 'Store', 'cloud' => 'Cloud', 'server' => 'Server', 'shield' => 'Security', 'link' => 'Integration'])->in(['code', 'store', 'cloud', 'server', 'shield', 'link'])->required()->default('code'),
                Textarea::make('summary')->required()->maxLength(1000)->rows(3)->columnSpanFull(),
                ContentFields::body(), TagsInput::make('offerings')->label('Deliverables')->nestedRecursiveRules(['string', 'max:160'])->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            ContentFields::publishing(), ContentFields::seo(),
        ]);
    }
}
