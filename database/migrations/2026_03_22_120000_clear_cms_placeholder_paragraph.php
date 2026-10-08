<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PLACEHOLDER = '<p>Edit this content in the staff CMS under <strong>Page content</strong>.</p>';

    public function up(): void
    {
        DB::table('cms_pages')
            ->where('body', self::PLACEHOLDER)
            ->update(['body' => null]);
    }

    public function down(): void
    {
        // Not restored: cannot know which null bodies were cleared by this migration.
    }
};
