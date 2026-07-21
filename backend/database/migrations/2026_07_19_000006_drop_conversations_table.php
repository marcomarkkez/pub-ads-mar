<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * design.md §10/§15 — `conversations` is superseded by `chats` (2026-07-17 merge).
 * OK to DROP old data (owner decision #10 — no data-preserving migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('conversations');
    }

    public function down(): void
    {
        // Recreate the pre-merge shape so a rollback is self-consistent.
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->nullable()->default('direct');
            $table->timestamp('support_joined_at')->nullable();
            $table->timestamps();
            $table->unique(['space_id', 'client_user_id', 'type']);
        });
    }
};
