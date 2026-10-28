<?php

namespace App\Filament\Resources\SiteSettings\Pages;

use App\Filament\Pages\SeoSettings;
use App\Filament\Resources\SiteSettings\SiteSettingResource;
use App\Mail\SmtpTestMessage;
use App\Support\InquiryMailSettings;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EditSiteSetting extends EditRecord
{
    protected static string $resource = SiteSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('accountSettings')->label('Account & password')->url(route('filament.admin.auth.profile')),
            Action::make('seoSettings')->label('SEO settings')->url(SeoSettings::getUrl()),
            Action::make('testEmail')->label('Send test email')
                ->action(function (InquiryMailSettings $settings): void {
                    $this->authorizeAccess();
                    try {
                        $recipient = $settings->recipient();
                        $mailer = $settings->mailer();
                        if (! $recipient || ! $mailer) {
                            Notification::make()->title('Email is not configured')->body('Save valid SMTP settings and a notification recipient first.')->warning()->send();

                            return;
                        }
                        Mail::mailer($mailer)->to($recipient)->send(new SmtpTestMessage);
                        Notification::make()->title('Test email sent')->body('Check '.$recipient.', including the spam folder.')->success()->send();
                    } catch (Throwable) {
                        Notification::make()->title('Test email failed')->body('Check your saved SMTP host, port, encryption, credentials and sender address with your mail provider.')->danger()->send();
                    }
                }),
        ];
    }

    protected function afterSave(): void
    {
        $this->data['smtp_password'] = null;
    }
}
