<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Note: This functionality has been moved to migration 2025_05_01_000001_add_device_related_id_to_devices_table.php
        // This migration is kept to maintain backwards compatibility with existing systems
        if (!Schema::hasColumn('devices', 'device_type')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->string('device_type')->nullable()->after('type'); // 'mqtt', 'tuya', etc.
            });
        }
    }

    public function down(): void
    {
        // Do nothing as the column removal is handled by the other migration
    }
};
