<?php

namespace App\Filament\Support;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContentFields
{
    public static function title(string $name = 'title'): TextInput
    {
        return TextInput::make($name)->required()->maxLength(255)->live(onBlur: true)
            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                if (blank($get('slug'))) {
                    $set('slug', Str::slug($state ?? ''));
                }
            });
    }

    public static function slug(string $table): TextInput
    {
        return TextInput::make('slug')->required()->maxLength(255)
            ->live(onBlur: true)
            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
            ->unique(table: $table, ignoreRecord: true)
            ->helperText('Use lowercase English words separated by hyphens. Changing a published slug changes its URL.');
    }

    public static function category(string $type): Select
    {
        return Select::make('category_id')->label('Category')->required()
            ->relationship('category', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->where('type', $type))
            ->searchable()->preload()
            ->rules([Rule::exists('categories', 'id')->where('type', $type)]);
    }

    public static function image(string $name, string $directory): FileUpload
    {
        return FileUpload::make($name)->image()->disk('public')->directory($directory)
            ->visibility('public')->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(4096)->preventFilePathTampering()
            ->helperText('JPG, PNG, or WebP. Maximum 4 MB. Uploads are public.');
    }

    public static function body(string $name = 'body'): MarkdownEditor
    {
        return MarkdownEditor::make($name)->columnSpanFull()->maxLength(100000)->live(onBlur: true)
            ->toolbarButtons(['bold', 'italic', 'link', 'heading', 'bulletList', 'orderedList', 'blockquote', 'codeBlock']);
    }

    public static function publishing(bool $featured = false, bool $demo = false): Section
    {
        $fields = [
            Toggle::make('is_published')->label('Published')->default(false),
            DateTimePicker::make('published_at')->label('Publish date')->seconds(false)
                ->helperText('A future date schedules publication. Leave blank to publish immediately when Published is enabled.'),
            TextInput::make('sort_order')->numeric()->integer()->minValue(0)->maxValue(1000000)->default(0)->required(),
        ];
        if ($featured) {
            $fields[] = Toggle::make('is_featured')->label('Featured')->default(false);
        }
        if ($demo) {
            $fields[] = Toggle::make('is_demo')->label('Sample / concept content')->default(false);
        }

        return Section::make('Publishing')->schema($fields)->columns(2)->columnSpanFull();
    }

    public static function seo(?string $type = null): Section
    {
        if ($type) {
            return SeoFields::section($type);
        }

        return Section::make('Search appearance')->schema([
            TextInput::make('seo_title')->label('SEO title')->maxLength(255),
            Textarea::make('seo_description')->label('SEO description')->maxLength(320)->rows(3),
        ])->columns(2)->columnSpanFull()->collapsible();
    }
}
