<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->integer('wifi_strength')->nullable()->after('last_sync');
            $table->string('firmware_version')->nullable()->after('wifi_strength');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['wifi_strength', 'firmware_version']);
        });
    }
};
