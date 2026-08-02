<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-7/UC-22 · design.json §10/§15 — flags = volatile human annotations, with
 * history. On change the old flag is superseded (active=false, superseded_at set)
 * and a kind=system message records it. A flag NEVER grants access; the money
 * STATE stays on payments.status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->string('type'); // payment_held|refund|payout_hold|cancel|mismatch|other
            $table->text('reason')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->index(['chat_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_flags');
    }
};
