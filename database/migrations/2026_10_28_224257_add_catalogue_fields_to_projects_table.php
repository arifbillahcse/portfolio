<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('catalogue_key', 100)->nullable()->unique();
            $table->json('catalogue_metadata')->nullable();
            $table->text('video_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropUnique(['catalogue_key']);
            $table->dropColumn(['catalogue_key', 'catalogue_metadata', 'video_url']);
        });
    }
};
