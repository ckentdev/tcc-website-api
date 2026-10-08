<?php

use App\Support\CmsModule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('cms_modules')->nullable()->after('is_admin');
        });

        DB::table('users')
            ->where('is_admin', false)
            ->update(['cms_modules' => json_encode(CmsModule::ALL)]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('cms_modules');
        });
    }
};
