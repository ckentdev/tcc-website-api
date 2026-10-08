<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->string('thumbnail_url', 2048)->nullable();
            /** Academic program slug (matches public site academics section id) */
            $table->string('academic_program_id', 128)->index();
            $table->string('link_url', 2048)->nullable();
            $table->json('sdg_goals')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_pages');
    }
};
