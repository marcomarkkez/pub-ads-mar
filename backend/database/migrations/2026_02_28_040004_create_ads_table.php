<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->enum('media_type', ['image', 'video', 'sound', 'gif']);
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('pricing_unit')->nullable(); // day, month, custom
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected', 'active', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->timestamp('proof_deadline')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
