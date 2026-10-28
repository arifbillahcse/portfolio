<?php

namespace App\Filament\Resources\SeoRedirects;

use App\Filament\Resources\SeoRedirects\Pages\CreateSeoRedirect;
use App\Filament\Resources\SeoRedirects\Pages\EditSeoRedirect;
use App\Filament\Resources\SeoRedirects\Pages\ListSeoRedirects;
use App\Models\SeoRedirect;
use App\Support\SeoRedirects;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class SeoRedirectResource extends Resource
{
    protected static ?string $model = SeoRedirect::class;

    protected static ?string $navigationLabel = 'SEO redirects';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    public static function getNavigationGroup(): ?string
    {
        return 'SEO';
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function validateForm(array $data, ?SeoRedirect $record = null): array
    {
        $candidate = $record ? clone $record : new SeoRedirect;
        $candidate->fill($data);
        try {
            app(SeoRedirects::class)->validateRule($candidate);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors['data.'.$field] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }

        return [...$data, 'source_path' => $candidate->source_path, 'destination_path' => $candidate->destination_path];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('source_path')->required()->rule('string')->maxLength(2048)->helperText('Example: /old-page or /?p=123. Exact public paths only; no wildcards.'),
            TextInput::make('destination_path')->required()->rule('string')->maxLength(2048)->helperText('An existing published page on this website, such as /about. Redirect chains are resolved to their final public destination.'),
            Select::make('status_code')->options([301 => '301 · Permanent', 302 => '302 · Temporary'])->required()->in([301, 302])->default(301),
            Toggle::make('enabled')->default(false)->helperText('Review a suggestion before enabling it.'),
            Textarea::make('notes')->rule('string')->maxLength(1000)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('source_path')->searchable()->wrap(), TextColumn::make('destination_path')->wrap(),
            TextColumn::make('status_code'), IconColumn::make('enabled')->boolean(), TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->filters([TernaryFilter::make('enabled')])->defaultSort('updated_at', 'desc')
            ->recordActions([EditAction::make()])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->authorizeIndividualRecords()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSeoRedirects::route('/'), 'create' => CreateSeoRedirect::route('/create'), 'edit' => EditSeoRedirect::route('/{record}/edit')];
    }
}
