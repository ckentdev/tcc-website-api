<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_pages', function (Blueprint $table) {
            $table->string('org_chart_url', 2048)->nullable()->after('thumbnail_url');
            $table->longText('org_chart_body')->nullable()->after('org_chart_url');
        });
    }

    public function down(): void
    {
        Schema::table('program_pages', function (Blueprint $table) {
            $table->dropColumn(['org_chart_url', 'org_chart_body']);
        });
    }
};
