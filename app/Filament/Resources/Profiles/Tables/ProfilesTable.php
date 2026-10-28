<?php

namespace App\Filament\Resources\Profiles\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label('Profile'), TextColumn::make('headline')->wrap(), TextColumn::make('updated_at')->dateTime()])->recordActions([EditAction::make()]);
    }
}
