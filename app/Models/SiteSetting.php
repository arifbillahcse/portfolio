<?php

namespace App\Models;

use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'brand_name',
    'tagline',
    'contact_email',
    'whatsapp_number',
    'facebook_url',
    'linkedin_url',
    'github_url',
    'seo_title',
    'seo_description',
    'footer_text',
])]
class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [

        ];
    }
}
