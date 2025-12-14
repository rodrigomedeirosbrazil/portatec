<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->datetime('check_in');
            $table->datetime('check_out');
            $table->string('external_id')->nullable(); // ID do evento no iCal para rastreamento
            $table->string('deletion_reason')->nullable(); // Motivo da remoção (soft delete)
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['place_id', 'check_in', 'check_out']);
            $table->index(['integration_id']);
            $table->index(['external_id', 'integration_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
