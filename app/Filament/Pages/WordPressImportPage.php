<?php

namespace App\Filament\Pages;

use App\Models\WordPressImport;
use App\Support\WordPressImporter;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

class WordPressImportPage extends Page
{
    use WithPagination;

    protected static ?string $title = 'WordPress import';

    protected static ?string $slug = 'wordpress-import';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected string $view = 'filament.pages.word-press-import-page';

    #[Locked]
    public ?int $runId = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $requested = session('wordpress_import_id');
        $this->runId = $requested && WordPressImport::query()->whereKey($requested)->exists()
            ? (int) $requested : WordPressImport::query()->latest('id')->value('id');
    }

    public function selectRun(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $this->runId = WordPressImport::query()->findOrFail($id)->id;
        $this->resetPage();
    }

    public function resumeImport(): void
    {
        abort_unless(static::canAccess(), 403);
        $import = WordPressImport::query()->findOrFail($this->runId);
        if ($import->items()->whereIn('status', ['queued', 'processing'])->where('updated_at', '>', now()->subMinutes(2))->exists()) {
            Notification::make()->title('Import is still active. Wait two minutes before resuming.')->warning()->send();

            return;
        }
        app(WordPressImporter::class)->resume($import);
        Notification::make()->title('Unfinished posts and failed images queued again.')->success()->send();
    }

    protected function getViewData(): array
    {
        $run = $this->runId ? WordPressImport::query()->find($this->runId) : null;

        return [
            'runs' => WordPressImport::query()->latest('id')->limit(20)->get(),
            'run' => $run,
            'counts' => $run?->items()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all() ?? [],
            'items' => $run?->items()->orderBy('id')->paginate(20),
            'phpUploadLimit' => ini_get('upload_max_filesize'),
            'phpPostLimit' => ini_get('post_max_size'),
        ];
    }
}
