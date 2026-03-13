<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuya_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tuya_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('place_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_id');
            $table->string('name');
            $table->string('category')->nullable();
            $table->boolean('online')->default(false);
            $table->json('status')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['tuya_account_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuya_devices');
    }
};
