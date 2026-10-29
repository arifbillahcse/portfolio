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
    'page_seo',
    'seo_title_templates',
    'footer_text',
    'google_site_verification',
    'chat_widget_enabled',
    'chat_widget_code',
    'chat_widget_load_mode',
    'lead_notifications_enabled',
    'lead_notification_email',
    'smtp_enabled',
    'smtp_host',
    'smtp_port',
    'smtp_encryption',
    'smtp_username',
    'smtp_password',
    'smtp_from_address',
    'smtp_from_name',
])]
class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory;

    protected $hidden = ['smtp_password'];

    protected function casts(): array
    {
        return [
            'page_seo' => 'array',
            'seo_title_templates' => 'array',
            'chat_widget_enabled' => 'boolean',
            'lead_notifications_enabled' => 'boolean',
            'smtp_enabled' => 'boolean',
            'smtp_port' => 'integer',
            'smtp_password' => 'encrypted',
        ];
    }
}
