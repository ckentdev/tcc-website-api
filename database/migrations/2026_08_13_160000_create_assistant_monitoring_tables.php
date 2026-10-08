<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_visitors', function (Blueprint $table) {
            $table->id();
            $table->uuid('visitor_key')->unique();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser', 64)->nullable();
            $table->string('platform', 64)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained('assistant_visitors')->cascadeOnDelete();
            $table->string('source', 16)->default('mini');
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['visitor_id', 'last_message_at']);
        });

        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('assistant_conversations')->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('body');
            $table->boolean('needs_followup')->default(false);
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });

        Schema::create('assistant_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained('assistant_visitors')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('assistant_conversations')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('summary')->nullable();
            $table->string('priority', 16)->default('high');
            $table->string('status', 24)->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('staff_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_tickets');
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_conversations');
        Schema::dropIfExists('assistant_visitors');
    }
};
