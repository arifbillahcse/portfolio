<?php

namespace App\Models;

use Database\Factories\ResumeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'type',
    'title',
    'organization',
    'location',
    'start_label',
    'end_label',
    'is_current',
    'description',
    'sort_order',
    'is_published',
])]
class ResumeEntry extends Model
{
    /** @use HasFactory<ResumeEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }
}
