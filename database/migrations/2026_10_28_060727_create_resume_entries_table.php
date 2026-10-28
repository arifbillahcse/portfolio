<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 30);
            $table->string('title');
            $table->string('organization')->nullable();
            $table->string('location')->nullable();
            $table->string('start_label', 50)->nullable();
            $table->string('end_label', 50)->nullable();
            $table->boolean('is_current')->default(false);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->index(['type', 'is_published', 'sort_order']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_entries');
    }
};
