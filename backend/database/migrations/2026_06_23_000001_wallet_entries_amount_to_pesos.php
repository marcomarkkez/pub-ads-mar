<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Money = decimal MXN pesos everywhere (owner decision 2026-06-23: "forget cents").
 * wallet_entries was the only money store using integer centavos; convert it to
 * decimal(12,2) pesos to match bookings/payments/ads/spaces/campaigns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_entries', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->default(0)->after('amount_centavos');
        });

        DB::statement('UPDATE wallet_entries SET amount = amount_centavos / 100.0');

        Schema::table('wallet_entries', function (Blueprint $table) {
            $table->dropColumn('amount_centavos');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_entries', function (Blueprint $table) {
            $table->bigInteger('amount_centavos')->default(0)->after('amount');
        });

        DB::statement('UPDATE wallet_entries SET amount_centavos = ROUND(amount * 100)');

        Schema::table('wallet_entries', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
};
