<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-8/UC-9 · design.json §10/§15 — messages now belong to a `chat`, not a
 * `conversation`. Drop the old FK, rename the column, and repoint it at `chats`.
 * Order matters: this runs BEFORE conversations is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Drop the old FK to conversations.
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
        });

        // 2) Rename the column.
        Schema::table('messages', function (Blueprint $table) {
            $table->renameColumn('conversation_id', 'chat_id');
        });

        // 3) Repoint the FK at chats.
        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('chat_id')->references('id')->on('chats')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['chat_id']);
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->renameColumn('chat_id', 'conversation_id');
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
        });
    }
};
