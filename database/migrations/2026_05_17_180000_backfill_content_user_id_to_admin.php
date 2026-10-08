<?php

use App\Models\Article;
use App\Models\CampusEvent;
use App\Models\ResearchPublication;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $email = strtolower((string) config('cms.admin_email'));
        if ($email === '') {
            return;
        }

        $admin = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $admin) {
            return;
        }

        Article::query()->whereNull('user_id')->update(['user_id' => $admin->id]);
        CampusEvent::query()->whereNull('user_id')->update(['user_id' => $admin->id]);
        ResearchPublication::query()->whereNull('user_id')->update(['user_id' => $admin->id]);
    }

    public function down(): void
    {
        // Non-reversible: prior user_id values are unknown.
    }
};
