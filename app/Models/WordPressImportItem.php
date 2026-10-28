<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['word_press_import_id', 'post_id', 'title', 'status', 'payload', 'error'])]
class WordPressImportItem extends Model
{
    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
