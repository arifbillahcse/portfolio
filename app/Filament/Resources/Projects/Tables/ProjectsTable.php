<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Filament\Support\ContentTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return ContentTable::configure($table, categoryType: 'project', hasDemo: true)->pushFilters([
            SelectFilter::make('catalogue_source')->label('Import source')->options([
                'arif-cloud-portfolio' => 'Cloud portfolio',
                'arif-web-development' => 'Website development',
            ])->query(function (Builder $query, array $data): Builder {
                $source = $data['value'] ?? null;

                return in_array($source, ['arif-cloud-portfolio', 'arif-web-development'], true)
                    ? $query->where('catalogue_key', 'like', $source.':%')
                    : $query;
            }),
        ]);
    }
}
