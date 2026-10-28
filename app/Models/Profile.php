<?php

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'name',
    'headline',
    'intro',
    'biography',
    'portrait_path',
    'journey',
    'skill_groups',
    'stats',
    'vision_quote',
    'vision_note',
    'home_heading',
    'home_intro',
    'businesses',
    'support_story',
])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'journey' => 'array',
            'skill_groups' => 'array',
            'stats' => 'array',
            'businesses' => 'array',
        ];
    }
}
