<?php

namespace App\Filament\Resources\Inquiries\Pages;

use App\Filament\Resources\Inquiries\InquiryResource;
use App\Support\InquiryNotifier;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditInquiry extends EditRecord
{
    protected static string $resource = InquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retryNotification')->label('Retry email notification')->requiresConfirmation()
                ->visible(fn (): bool => in_array($this->record->notification_status, ['failed', 'not_configured'], true))
                ->action(function (InquiryNotifier $notifier): void {
                    $queued = $notifier->queue($this->record);
                    Notification::make()->title($queued ? 'Email notification queued' : 'Email could not be queued')
                        ->body($queued ? 'A queue worker will deliver the notification.' : 'Check mail configuration and queue availability. The inquiry is saved in your inbox.')
                        ->status($queued ? 'success' : 'warning')->send();
                    $this->refreshFormData(['notification_status', 'emailed_at']);
                }),
        ];
    }
}
