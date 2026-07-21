<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-8 · design.md §10/§15 — polymorphic N attachments on a chat
 * (Ad|Adset|Campaign|Space|Payment|Booking). Replaces the old single space_id
 * anchor AND the retired `ticketable`. Attaching is OWNERSHIP-CHECKED (R2/§16).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->morphs('objectable'); // objectable_type + objectable_id (+ index)
            $table->foreignId('attached_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('chat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_objects');
    }
};
