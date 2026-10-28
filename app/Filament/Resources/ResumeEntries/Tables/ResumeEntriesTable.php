<?php

namespace App\Filament\Resources\ResumeEntries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ResumeEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable()->wrap(), TextColumn::make('type')->badge(),
            TextColumn::make('organization')->searchable(), IconColumn::make('is_published')->boolean(),
            TextColumn::make('sort_order')->label('Order')->sortable(),
        ])->filters([SelectFilter::make('type')->options(['education' => 'Education', 'experience' => 'Experience', 'learning' => 'Learning', 'extracurricular' => 'Extracurricular']), TernaryFilter::make('is_published')])
            ->defaultSort('sort_order')->reorderable('sort_order')->paginationPageOptions([10, 25, 50])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->authorizeIndividualRecords()])]);
    }
}
