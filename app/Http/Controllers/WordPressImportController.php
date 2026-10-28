<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWordPressImportRequest;
use App\Support\WordPressImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class WordPressImportController extends Controller
{
    public function store(StoreWordPressImportRequest $request, WordPressImporter $importer): RedirectResponse
    {
        $file = $request->file('xml');
        try {
            $import = $importer->start($file->getRealPath(), $file->getClientOriginalName());
        } catch (ValidationException $exception) {
            return redirect()->route('filament.admin.pages.wordpress-import')->withErrors($exception->errors());
        }

        return redirect()->route('filament.admin.pages.wordpress-import')
            ->with('wordpress_import_id', $import->id)
            ->with('wordpress_import_success', 'Import #'.$import->id.' queued: '.$import->total.' posts. Images will download in the background.');
    }
}
