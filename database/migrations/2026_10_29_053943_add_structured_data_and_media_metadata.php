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
                $table->string('seo_schema_type', 30)->nullable();
                $table->json('body_image_metadata')->nullable();
            });
        }
        foreach (['posts', 'projects'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('cover_image_alt')->nullable();
                $table->string('cover_image_caption', 500)->nullable();
            });
        }
        Schema::table('profiles', function (Blueprint $table): void {
            $table->string('portrait_alt')->nullable();
        });
        Schema::table('projects', function (Blueprint $table): void {
            $table->json('gallery_metadata')->nullable();
            $table->boolean('video_schema_enabled')->default(false);
            $table->string('video_name')->nullable();
            $table->text('video_description')->nullable();
            $table->string('video_thumbnail')->nullable();
            $table->timestamp('video_uploaded_at')->nullable();
            $table->unsignedInteger('video_duration_seconds')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['posts', 'projects', 'services'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['seo_schema_type', 'body_image_metadata']));
        }
        foreach (['posts', 'projects'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['cover_image_alt', 'cover_image_caption']));
        }
        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn('portrait_alt'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['gallery_metadata', 'video_schema_enabled', 'video_name', 'video_description', 'video_thumbnail', 'video_uploaded_at', 'video_duration_seconds']));
    }
};
