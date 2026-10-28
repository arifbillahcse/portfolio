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
                TextInput::make('contact_email')->email()->required()->maxLength(254),
                TextInput::make('whatsapp_number')->tel()->required()->regex('/^\+?[1-9][0-9]{6,14}$/')->maxLength(16)->helperText('Use country code, without spaces. Example: +8801779440297'),
                TextInput::make('facebook_url')->url()->regex('/^https?:\/\//i')->maxLength(2048),
                TextInput::make('linkedin_url')->url()->regex('/^https?:\/\//i')->maxLength(2048),
                TextInput::make('github_url')->url()->regex('/^https?:\/\//i')->maxLength(2048),
            ])->columns(2)->columnSpanFull(),
            ContentFields::seo(),
        ]);
    }
}
