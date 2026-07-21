<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-21 · design.md §10/§15 — PER-PERSON membership table (owner chose the full
 * table). Access is by MEMBERSHIP/role, never by a mutable flag. A Support join
 * writes an announced=true row; Admin silent entry is a forensic (announced=false)
 * row. Collaborator access is still derived from the account overlay at request time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('side'); // client|provider|support|payments|admin
            $table->boolean('announced')->default(false);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['chat_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_participants');
    }
};
