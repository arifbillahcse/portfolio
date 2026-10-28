<?php

namespace App\Filament\Support;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContentTable
{
    public static function configure(Table $table, ?string $categoryType = null, bool $hasDemo = false): Table
    {
        $columns = [TextColumn::make('title')->searchable()->sortable()->wrap()];
        $filters = [TernaryFilter::make('is_published')->label('Published')];
        if ($categoryType) {
            $columns[] = TextColumn::make('category.name')->label('Category')->badge();
            $filters[] = SelectFilter::make('category_id')->label('Category')
                ->relationship('category', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->where('type', $categoryType));
        }
        if ($hasDemo) {
            $columns[] = IconColumn::make('is_demo')->label('Sample')->boolean();
            $filters[] = TernaryFilter::make('is_demo')->label('Sample content');
        }
        $columns[] = IconColumn::make('is_published')->label('Published')->boolean();
        $columns[] = TextColumn::make('published_at')->dateTime()->sortable()->toggleable();
        $columns[] = TextColumn::make('sort_order')->label('Order')->sortable();
        $columns[] = TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true);

        return $table->columns($columns)->filters($filters)->defaultSort('sort_order')
            ->reorderable('sort_order')->paginationPageOptions([10, 25, 50])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->authorizeIndividualRecords()])]);
    }
}
