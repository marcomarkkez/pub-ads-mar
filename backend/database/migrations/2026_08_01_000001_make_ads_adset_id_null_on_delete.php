<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * design.json §21 — an adset is only a GROUPING label, so deleting it must NOT
 * destroy its ads. `ads.adset_id` moves from `cascadeOnDelete` (set by
 * 2026_06_13_000020) to `nullOnDelete`: on delete the ads survive as backlog
 * orphans in their campaign.
 *
 * Orphaned is not stopped — anything already paid keeps running; an orphan is
 * just harder to find for further actions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropForeign(['adset_id']);
        });

        Schema::table('ads', function (Blueprint $table) {
            $table->foreign('adset_id')->references('id')->on('adsets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropForeign(['adset_id']);
        });

        Schema::table('ads', function (Blueprint $table) {
            $table->foreign('adset_id')->references('id')->on('adsets')->cascadeOnDelete();
        });
    }
};
