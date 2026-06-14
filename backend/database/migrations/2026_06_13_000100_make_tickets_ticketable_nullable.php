<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reference-less support tickets (B2): a ticket can be opened from the Help menu
 * with no attached object, so ticketable_type/id must be nullable. The
 * proof-mismatch auto-ticket still always sets ticketable = Ad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('ticketable_type')->nullable()->change();
            $table->unsignedBigInteger('ticketable_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('ticketable_type')->nullable(false)->change();
            $table->unsignedBigInteger('ticketable_id')->nullable(false)->change();
        });
    }
};
