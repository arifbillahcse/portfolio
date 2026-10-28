<?php

namespace App\Filament\Resources\SiteSettings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SiteSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([TextColumn::make('brand_name'), TextColumn::make('contact_email'), TextColumn::make('updated_at')->dateTime()])->recordActions([EditAction::make()]);
    }
}
