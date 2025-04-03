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
        Schema::create('tuya_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tuya_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('device_id')->nullable();
            $table->string('local_key')->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_online')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tuya_devices');
    }
};
