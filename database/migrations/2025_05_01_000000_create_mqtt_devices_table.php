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
        Schema::create('mqtt_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('topic')->nullable();
            $table->string('command_topic')->nullable();
            $table->string('payload_on')->nullable();
            $table->string('payload_off')->nullable();
            $table->string('availability_topic')->nullable();
            $table->string('availability_payload_on')->nullable();
            $table->boolean('is_available')->default(true);
            $table->string('json_attribute')->nullable();
            $table->timestamps();

            $table->index(['topic']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mqtt_devices');
    }
};
