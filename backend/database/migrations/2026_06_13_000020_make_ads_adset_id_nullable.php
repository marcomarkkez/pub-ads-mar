<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // C03: an ad with adset_id = null + a space_id is a backlog/orphan item
        // sitting in a campaign, not yet placed into an adset.
        Schema::table('ads', function (Blueprint $table) {
            $table->dropForeign(['adset_id']);
        });

        Schema::table('ads', function (Blueprint $table) {
            $table->foreignId('adset_id')->nullable()->change();
            $table->foreign('adset_id')->references('id')->on('adsets')->cascadeOnDelete();
        });

        // Orphan ads carry the campaign linkage directly while unplaced.
        Schema::table('ads', function (Blueprint $table) {
            if (! Schema::hasColumn('ads', 'campaign_id')) {
                $table->foreignId('campaign_id')->nullable()->after('id')
                    ->constrained('campaigns')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            if (Schema::hasColumn('ads', 'campaign_id')) {
                $table->dropForeign(['campaign_id']);
                $table->dropColumn('campaign_id');
            }
        });

        Schema::table('ads', function (Blueprint $table) {
            $table->dropForeign(['adset_id']);
        });

        Schema::table('ads', function (Blueprint $table) {
            $table->foreignId('adset_id')->nullable(false)->change();
            $table->foreign('adset_id')->references('id')->on('adsets')->cascadeOnDelete();
        });
    }
};
