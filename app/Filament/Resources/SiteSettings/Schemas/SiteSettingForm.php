<?php

namespace App\Filament\Resources\SiteSettings\Schemas;

use App\Filament\Support\ContentFields;
use App\Models\SiteSetting;
use App\Support\GoogleReporting;
use App\Support\SiteIntegrations;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

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
            Section::make('Lead email notifications')->description('Email notifications for new contact and Hire form submissions. Leads are always saved in Inquiries.')->schema([
                Toggle::make('lead_notifications_enabled')->label('Email me when a new lead arrives')->default(true),
                TextInput::make('lead_notification_email')->label('Notification recipient email')->email()->maxLength(254)
                    ->helperText('One inbox for lead notifications. Leave blank to use your public contact email. This address is not shown to visitors.'),
            ])->columns(2)->columnSpanFull(),
            Section::make('SMTP email delivery')->description('Save your settings, then use Send test email. Lead notifications use the mail queue; keep the cPanel queue Cron Job running.')->schema([
                Toggle::make('smtp_enabled')->label('Use these SMTP settings')->default(false)->live()
                    ->helperText('When off, the existing server mail configuration is used.')->columnSpanFull(),
                TextInput::make('smtp_host')->label('SMTP host')->maxLength(255)->regex('/\A[a-zA-Z0-9][a-zA-Z0-9.-]*\z/D')
                    ->required(fn (Get $get): bool => (bool) $get('smtp_enabled'))->helperText('Hostname only, for example mail.arifbillah.com.'),
                TextInput::make('smtp_port')->label('SMTP port')->numeric()->rules(['integer'])->minValue(1)->maxValue(65535)->default(587)
                    ->dehydrateStateUsing(fn (mixed $state): int => filled($state) ? (int) $state : 587)
                    ->required(fn (Get $get): bool => (bool) $get('smtp_enabled')),
                Select::make('smtp_encryption')->label('Encryption')->options(['tls' => 'STARTTLS (usually port 587)', 'ssl' => 'SSL / TLS (usually port 465)'])->default('tls')
                    ->dehydrateStateUsing(fn (?string $state): string => $state ?: 'tls')
                    ->rules([Rule::in(['tls', 'ssl'])])->required(fn (Get $get): bool => (bool) $get('smtp_enabled')),
                TextInput::make('smtp_username')->label('SMTP username')->maxLength(255)->autocomplete('off')
                    ->required(fn (Get $get): bool => (bool) $get('smtp_enabled')),
                TextInput::make('smtp_password')->label('SMTP password')->password()->autocomplete('new-password')->rule('string')->maxLength(2048)->trim(false)
                    ->required(fn (Get $get, ?SiteSetting $record): bool => (bool) $get('smtp_enabled') && ! filled($record?->getRawOriginal('smtp_password')))
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Stored encrypted. Leave blank to keep the saved password.'),
                TextInput::make('smtp_from_address')->label('Sender email address')->email()->maxLength(254)
                    ->required(fn (Get $get): bool => (bool) $get('smtp_enabled'))->helperText('Use an email address authorized by your SMTP provider.'),
                TextInput::make('smtp_from_name')->label('Sender name')->maxLength(255)->regex('/\A[^\r\n]*\z/D')
                    ->helperText('Leave blank to use your brand name.'),
            ])->columns(2)->columnSpanFull(),
            Section::make('Google verification')->description('Add the HTML-tag verification supplied by Google Search Console.')->schema([
                TextInput::make('google_site_verification')->label('Google site verification')->maxLength(2048)
                    ->rule('string')->regex(SiteIntegrations::GOOGLE_TOKEN_PATTERN)
                    ->mutateStateForValidationUsing(fn (mixed $state): mixed => SiteIntegrations::googleVerificationToken($state))
                    ->dehydrateStateUsing(fn (mixed $state): mixed => SiteIntegrations::googleVerificationToken($state))
                    ->helperText('Paste the token or the complete <meta name="google-site-verification" ...> tag. It is added to the head of every public portfolio page. Leave blank to remove it.')
                    ->columnSpanFull(),
            ])->columnSpanFull(),
            Section::make('Analytics & growth')->description('GA4 tracking loads only on public pages in production, and is skipped for visitors who send Do Not Track or Global Privacy Control. Reporting needs a Google service account added as a viewer in GA4 and Search Console.')->schema([
                Toggle::make('analytics_enabled')->label('Enable GA4 tracking and lead events')->default(false)->live()->columnSpanFull(),
                TextInput::make('ga4_measurement_id')->label('GA4 measurement ID')->placeholder('G-XXXXXXXXXX')->maxLength(32)
                    ->regex('/\AG-[A-Z0-9]{4,20}\z/D')->required(fn (Get $get): bool => (bool) $get('analytics_enabled')),
                TextInput::make('ga4_property_id')->label('GA4 property ID (reports)')->maxLength(20)->regex('/\A[0-9]{3,20}\z/D')
                    ->helperText('Numeric ID from GA4 Admin → Property details.'),
                TextInput::make('search_console_property')->label('Search Console property')->maxLength(255)
                    ->regex('/\A(?:sc-domain:[a-z0-9.-]+|https?:\/\/[^\s]+\/)\z/iD')
                    ->helperText('Example: sc-domain:example.com or https://example.com/ (with trailing slash).'),
                Textarea::make('google_service_account')->label('Google service account JSON')->rows(5)->maxLength(10000)->autocomplete('off')
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (filled($value) && ! app(GoogleReporting::class)->credentials((new SiteSetting)->forceFill(['google_service_account' => $value]))) {
                            $fail('Paste the complete service account JSON key file.');
                        }
                    })->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Stored encrypted and never shown again. Leave blank to keep the saved key.')->columnSpanFull(),
                DatePicker::make('next_content_review_on')->label('Next content review'),
                Textarea::make('content_plan')->label('Ongoing content plan')->rows(4)->maxLength(5000)->columnSpanFull()
                    ->helperText('Planned articles, case studies and updates. Shown on the Growth dashboard.'),
            ])->columns(2)->columnSpanFull(),
            Section::make('Chat widget')->description('Add the embed snippet supplied by your chat provider.')->schema([
                Toggle::make('chat_widget_enabled')->label('Enable chat widget')->default(false)->live(),
                Select::make('chat_widget_load_mode')->label('Chat loading')->options([
                    'immediate' => 'Immediately (existing behavior)', 'idle' => 'After page load / idle', 'interaction' => 'When Open chat is clicked',
                ])->default('immediate')->required()->in(['immediate', 'idle', 'interaction'])
                    ->helperText('Deferred modes reduce startup work. Use Immediately if the provider relies on document.write or cannot be loaded dynamically.'),
                Textarea::make('chat_widget_code')->label('Chat widget embed code')->rule('string')->rows(10)->maxLength(30000)
                    ->required(fn (Get $get): bool => (bool) $get('chat_widget_enabled'))
                    ->helperText('Paste the complete HTML / JavaScript embed code. It runs on public pages using the selected loading mode. It does not run inside the admin panel or private previews.')
                    ->columnSpanFull(),
            ])->columnSpanFull(),
        ]);
    }
}
