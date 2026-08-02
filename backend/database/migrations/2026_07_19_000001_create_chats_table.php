<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-8/UC-9 · design.json §10/§15 — the ONE communication primitive `chats`.
 * A "ticket" is just a chat with a Support participant; nature is DERIVED from
 * participants+objects+flags, never a `type` column. NO space_id, NO unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opened_by_user_id')->constrained('users')->cascadeOnDelete();
            // Side anchors (MVP 1 user = 1 account). Nullable: a general "contact
            // support" chat has no provider; an internal Support↔Payments chat has
            // neither. Nature is derived from which anchors are present (§10/§16).
            $table->foreignId('client_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('provider_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open'); // open|in_progress|resolved|closed
            $table->timestamp('support_joined_at')->nullable(); // PII-relax + announced trigger
            $table->timestamp('resolved_at')->nullable();       // Support did its part
            $table->timestamp('closed_at')->nullable();         // opener confirmed solved
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();   // denormalized list ordering
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
