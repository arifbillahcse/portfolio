<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('type')->badge()->sortable(),
            TextColumn::make('slug')->searchable(), TextColumn::make('sort_order')->label('Order')->sortable(),
        ])->filters([SelectFilter::make('type')->options(['project' => 'Projects', 'post' => 'Blog posts'])])
            ->defaultSort('sort_order')->reorderable('sort_order')
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->authorizeIndividualRecords()])]);
    }
}
