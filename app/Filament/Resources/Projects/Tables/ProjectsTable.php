<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Filament\Support\ContentTable;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return ContentTable::configure($table, categoryType: 'project', hasDemo: true);
    }
}
