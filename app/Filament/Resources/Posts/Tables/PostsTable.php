<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Filament\Support\ContentTable;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return ContentTable::configure($table, categoryType: 'post', hasDemo: true);
    }
}
