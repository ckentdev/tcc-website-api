<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_publications', function (Blueprint $table) {
            $table->string('link_url', 2048)->nullable()->after('venue_or_journal');
        });
    }

    public function down(): void
    {
        Schema::table('research_publications', function (Blueprint $table) {
            $table->dropColumn('link_url');
        });
    }
};
