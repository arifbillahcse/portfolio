<?php

namespace App\Filament\Resources\ResumeEntries;

use App\Filament\Resources\ResumeEntries\Pages\CreateResumeEntry;
use App\Filament\Resources\ResumeEntries\Pages\EditResumeEntry;
use App\Filament\Resources\ResumeEntries\Pages\ListResumeEntries;
use App\Filament\Resources\ResumeEntries\Schemas\ResumeEntryForm;
use App\Filament\Resources\ResumeEntries\Tables\ResumeEntriesTable;
use App\Models\ResumeEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ResumeEntryResource extends Resource
{
    protected static ?string $model = ResumeEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Resume Entries';

    protected static ?int $navigationSort = 6;

    public static function getNavigationGroup(): ?string
    {
        return 'About & Resume';
    }

    public static function form(Schema $schema): Schema
    {
        return ResumeEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResumeEntriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResumeEntries::route('/'),
            'create' => CreateResumeEntry::route('/create'),
            'edit' => EditResumeEntry::route('/{record}/edit'),
        ];
    }
}
