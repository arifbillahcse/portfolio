<?php

namespace App\Filament\Resources\SiteSettings\Schemas;

use App\Filament\Support\ContentFields;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SiteSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Brand')->schema([
                TextInput::make('brand_name')->required()->maxLength(255), TextInput::make('tagline')->maxLength(255),
                TextInput::make('footer_text')->maxLength(255)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            Section::make('Contact & social links')->schema([
                TextInput::make('contact_email')->label('Public contact email')->email()->required()->maxLength(254)->helperText('Shown to clients. Change your sign-in email in Account settings.'),
                TextInput::make('whatsapp_number')->tel()->required()->stripCharacters([' ', '-', '(', ')'])->regex('/^\+?[1-9][0-9]{6,14}$/')->maxLength(16)->helperText('Include the country code. Example: +8801779440297. Spaces, brackets and hyphens are removed.'),
                TextInput::make('facebook_url')->url()->regex('/^https?:\/\//i')->maxLength(2048),
                TextInput::make('linkedin_url')->url()->regex('/^https?:\/\//i')->maxLength(2048),
                TextInput::make('github_url')->url()->regex('/^https?:\/\//i')->maxLength(2048),
            ])->description('Social profile links must start with https://.')->columns(2)->columnSpanFull(),
            ContentFields::seo(),
        ]);
    }
}
