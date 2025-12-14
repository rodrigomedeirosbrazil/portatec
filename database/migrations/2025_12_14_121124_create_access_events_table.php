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
        Schema::create('access_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('access_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pin', 6);
            $table->enum('result', ['success', 'failed', 'expired', 'invalid']);
            $table->timestamp('device_timestamp')->nullable();
            $table->timestamp('server_timestamp')->useCurrent();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'created_at']);
            $table->index(['access_code_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_events');
    }
};
