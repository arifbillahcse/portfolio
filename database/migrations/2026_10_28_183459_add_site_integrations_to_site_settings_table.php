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
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('google_site_verification', 512)->nullable();
            $table->boolean('chat_widget_enabled')->default(false);
            $table->longText('chat_widget_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn(['google_site_verification', 'chat_widget_enabled', 'chat_widget_code']);
        });
    }
};
