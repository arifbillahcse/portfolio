<?php

namespace App\Models;

use App\Support\SeoRedirects;
use Database\Factories\SeoRedirectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['source_path', 'destination_path', 'status_code', 'enabled', 'notes'])]
class SeoRedirect extends Model
{
    protected $attributes = ['status_code' => 301, 'enabled' => false];

    /** @use HasFactory<SeoRedirectFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'status_code' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (SeoRedirect $redirect): void {
            app(SeoRedirects::class)->validateRule($redirect);
        });
    }
}
