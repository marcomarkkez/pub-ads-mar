<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * design.json §7/§10/§11: a single space+client can now carry MORE than one thread
 * (the direct client↔provider chat PLUS the Support-anchored dispute threads:
 * internal / support_client / support_provider). The old unique(space_id,
 * client_user_id) blocked that — widen it to include `type`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['space_id', 'client_user_id']);
            $table->unique(['space_id', 'client_user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['space_id', 'client_user_id', 'type']);
            $table->unique(['space_id', 'client_user_id']);
        });
    }
};
