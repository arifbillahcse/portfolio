<?php

namespace App\Filament\Resources\SiteSettings\Schemas;

use App\Filament\Support\ContentFields;
use App\Support\SiteIntegrations;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
            Section::make('Google verification')->description('Add the HTML-tag verification supplied by Google Search Console.')->schema([
                TextInput::make('google_site_verification')->label('Google site verification')->maxLength(2048)
                    ->rule('string')->regex(SiteIntegrations::GOOGLE_TOKEN_PATTERN)
                    ->mutateStateForValidationUsing(fn (mixed $state): mixed => SiteIntegrations::googleVerificationToken($state))
                    ->dehydrateStateUsing(fn (mixed $state): mixed => SiteIntegrations::googleVerificationToken($state))
                    ->helperText('Paste the token or the complete <meta name="google-site-verification" ...> tag. It is added to the head of every public portfolio page. Leave blank to remove it.')
                    ->columnSpanFull(),
            ])->columnSpanFull(),
            Section::make('Chat widget')->description('Add the embed snippet supplied by your chat provider.')->schema([
                Toggle::make('chat_widget_enabled')->label('Enable chat widget')->default(false)->live(),
                Textarea::make('chat_widget_code')->label('Chat widget embed code')->rule('string')->rows(10)->maxLength(30000)
                    ->required(fn (Get $get): bool => (bool) $get('chat_widget_enabled'))
                    ->helperText('Paste the complete HTML / JavaScript embed code. When enabled, it runs immediately before </body> on public portfolio pages. It does not run inside the admin panel.')
                    ->columnSpanFull(),
            ])->columnSpanFull(),
        ]);
    }
}
