<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_pages', function (Blueprint $table) {
            $table->string('info_pdf_url', 2048)->nullable()->after('body');
            $table->string('info_pdf_name', 255)->nullable()->after('info_pdf_url');
        });
    }

    public function down(): void
    {
        Schema::table('program_pages', function (Blueprint $table) {
            $table->dropColumn(['info_pdf_url', 'info_pdf_name']);
        });
    }
};
