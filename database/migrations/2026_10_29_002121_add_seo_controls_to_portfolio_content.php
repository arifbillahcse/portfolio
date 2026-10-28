<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['posts', 'projects', 'services'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('seo_focus_keyword')->nullable();
                $table->json('seo_related_keywords')->nullable();
                $table->text('seo_canonical_url')->nullable();
                $table->string('seo_robots', 30)->nullable();
                $table->string('seo_social_title')->nullable();
                $table->text('seo_social_description')->nullable();
                $table->string('seo_social_image')->nullable();
                $table->string('seo_social_image_alt')->nullable();
            });
        }
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->json('page_seo')->nullable();
            $table->json('seo_title_templates')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['posts', 'projects', 'services'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn(['seo_focus_keyword', 'seo_related_keywords', 'seo_canonical_url', 'seo_robots',
                    'seo_social_title', 'seo_social_description', 'seo_social_image', 'seo_social_image_alt']);
            });
        }
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn(['page_seo', 'seo_title_templates']);
        });
    }
};
