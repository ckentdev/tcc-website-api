<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('seo_title')->nullable()->after('excerpt');
            $table->text('seo_description')->nullable()->after('seo_title');
            $table->string('og_image_alt')->nullable()->after('seo_description');
            $table->boolean('no_index')->default(false)->after('og_image_alt');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'seo_description', 'og_image_alt', 'no_index']);
        });
    }
};
