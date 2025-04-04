<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This migration should run after the MQTT data has been moved to the mqtt_devices table.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Remove index on topic column if it exists
            if (Schema::hasIndex('devices', 'devices_topic_index')) {
                $table->dropIndex('devices_topic_index');
            }

            // Remove columns that are now stored in mqtt_devices table
            $table->dropColumn([
                'topic',
                'command_topic',
                'payload_on',
                'payload_off',
                'availability_topic',
                'availability_payload_on',
                'is_available',
                'json_attribute'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Add back the columns if we need to rollback
            $table->string('topic')->nullable();
            $table->string('command_topic')->nullable();
            $table->string('payload_on')->nullable();
            $table->string('payload_off')->nullable();
            $table->string('availability_topic')->nullable();
            $table->string('availability_payload_on')->nullable();
            $table->boolean('is_available')->default(true);
            $table->string('json_attribute')->nullable();
        });
    }
};
