<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('content_type', 40);
            $table->unsignedBigInteger('content_id')->nullable();
            $table->string('content_slug', 200)->nullable();
            $table->string('event_type', 20);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['content_type', 'content_id', 'event_type'], 'cae_type_id_event_idx');
            $table->index(['content_type', 'content_slug', 'event_type'], 'cae_type_slug_event_idx');
            $table->index('created_at', 'cae_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_analytics_events');
    }
};
