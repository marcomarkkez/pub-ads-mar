<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['available', 'booked', 'blocked'])->default('available');
            $table->string('source')->default('manual'); // manual, ical
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_availabilities');
    }
};
