<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_publications', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('image_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            /** Academic program slug (matches public site academics section id) */
            $table->string('academic_program_id', 128)->index();
            /** publication | research */
            $table->string('output_type', 32)->index();
            $table->text('authors')->nullable();
            $table->string('venue_or_journal')->nullable();
            $table->json('sdg_goals')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_publications');
    }
};
