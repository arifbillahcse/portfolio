<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('source_path', 512)->unique();
            $table->text('destination_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('enabled')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('seo_not_found', function (Blueprint $table): void {
            $table->id();
            $table->char('path_hash', 64)->unique();
            $table->string('path', 512);
            $table->unsignedInteger('hits')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_not_found');
        Schema::dropIfExists('seo_redirects');
    }
};
