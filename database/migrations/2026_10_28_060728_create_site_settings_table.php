<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->default('main')->unique();
            $table->string('brand_name');
            $table->string('tagline')->nullable();
            $table->string('contact_email');
            $table->string('whatsapp_number', 30);
            $table->text('facebook_url')->nullable();
            $table->text('linkedin_url')->nullable();
            $table->text('github_url')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('footer_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
