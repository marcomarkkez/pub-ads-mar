<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('type')->nullable()->default('direct')->after('provider_user_id');
            $table->timestamp('support_joined_at')->nullable()->after('type');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('kind')->nullable()->default('user')->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['type', 'support_joined_at']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
