<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->default('main')->unique();
            $table->string('name');
            $table->string('headline');
            $table->text('intro');
            $table->longText('biography')->nullable();
            $table->string('portrait_path')->nullable();
            $table->json('journey')->nullable();
            $table->json('skill_groups')->nullable();
            $table->json('stats')->nullable();
            $table->text('vision_quote')->nullable();
            $table->text('vision_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
