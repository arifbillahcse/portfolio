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
        Schema::create('word_press_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('filename');
            $table->unsignedInteger('total')->default(0);
            $table->timestamps();
        });
        Schema::table('posts', function (Blueprint $table): void {
            $table->unsignedBigInteger('wordpress_id')->nullable()->unique();
            $table->string('wordpress_path', 512)->nullable()->index();
        });
        Schema::create('word_press_import_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('word_press_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('status')->default('queued')->index();
            $table->json('payload');
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_press_import_items');
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn(['wordpress_id', 'wordpress_path']);
        });
        Schema::dropIfExists('word_press_imports');
    }
};
