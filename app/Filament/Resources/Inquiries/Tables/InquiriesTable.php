<?php

namespace App\Filament\Resources\Inquiries\Tables;

use App\Models\Inquiry;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InquiriesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('email')->searchable(),
            TextColumn::make('source')->badge(),
            TextColumn::make('status')->badge(),
            TextColumn::make('notification_status')->label('Email')->formatStateUsing(fn (string $state): string => Inquiry::NOTIFICATION_STATUSES[$state] ?? $state),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(Inquiry::STATUSES),
            SelectFilter::make('notification_status')->options(Inquiry::NOTIFICATION_STATUSES),
        ])->defaultSort('created_at', 'desc')->paginationPageOptions([10, 25, 50])->recordActions([EditAction::make()->label('Open')]);
    }
}
