<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_pages', function (Blueprint $table) {
            $table->json('org_chart_urls')->nullable()->after('org_chart_url');
        });

        $rows = DB::table('program_pages')
            ->whereNotNull('org_chart_url')
            ->where('org_chart_url', '!=', '')
            ->get(['id', 'org_chart_url']);

        foreach ($rows as $row) {
            DB::table('program_pages')->where('id', $row->id)->update([
                'org_chart_urls' => json_encode([$row->org_chart_url]),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('program_pages', function (Blueprint $table) {
            $table->dropColumn('org_chart_urls');
        });
    }
};
