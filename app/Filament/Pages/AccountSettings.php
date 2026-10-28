<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Profiles\ProfileResource;
use App\Filament\Resources\SiteSettings\SiteSettingResource;
use Closure;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\Rules\Password;

class AccountSettings extends EditProfile
{
    protected static ?string $title = 'Account settings';

    protected ?string $subheading = 'Manage your admin name, sign-in email and password. Public portfolio information is edited separately.';

    protected function beforeFill(): void
    {
        abort_unless((bool) Filament::auth()->user()?->is_admin, 403);
    }

    protected function beforeValidate(): void
    {
        abort_unless((bool) Filament::auth()->user()?->is_admin, 403);
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()->label('Sign-in email')->maxLength(254)
            ->helperText('Used to log in. The email shown to clients is in Site Settings.');
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->label('New password')->maxLength(72)
            ->rule(Password::min(12)->mixedCase()->numbers()->symbols())
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen((string) $value) > 72) {
                    $fail('The password must be no more than 72 bytes. Please use a shorter password.');
                }
            })
            ->helperText('Leave blank to keep your password. Use at least 12 characters, with uppercase and lowercase letters, a number and a symbol.');
    }

    protected function afterSave(): void
    {
        $this->data['currentPassword'] = null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publicProfile')->label('Public portfolio profile')->url(ProfileResource::getUrl()),
            Action::make('siteSettings')->label('Site settings')->url(SiteSettingResource::getUrl()),
        ];
    }
}
