<?php

namespace App\Filament\Resources\Inquiries\Schemas;

use App\Models\Inquiry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class InquiryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Client message')->schema([
                TextInput::make('name')->disabled(),
                TextInput::make('email')->disabled(),
                TextInput::make('phone')->disabled(),
                TextInput::make('source')->disabled(),
                Textarea::make('message')->rows(8)->disabled()->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            Section::make('Follow-up')->schema([
                Select::make('status')->options(Inquiry::STATUSES)->required()->rules([Rule::in(array_keys(Inquiry::STATUSES))]),
                TextInput::make('notification_status')->label('Email notification')->disabled()
                    ->formatStateUsing(fn (?string $state): string => Inquiry::NOTIFICATION_STATUSES[$state] ?? $state ?? ''),
                TextInput::make('emailed_at')->disabled(),
                Textarea::make('admin_notes')->rows(4)->maxLength(5000)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }
}
