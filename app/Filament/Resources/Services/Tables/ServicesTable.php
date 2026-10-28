<?php

namespace App\Filament\Resources\Services\Tables;

use App\Filament\Support\ContentTable;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return ContentTable::configure($table);
    }
}
