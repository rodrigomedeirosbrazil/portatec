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
        Schema::table('place_devices', function (Blueprint $table) {
            $table->integer('gpio')->after('device_id')->nullable();
            $table->string('type')->after('gpio')->nullable();
            $table->unique(['device_id', 'gpio']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('place_devices', function (Blueprint $table) {
            $table->dropUnique(['device_id', 'gpio']);
            $table->dropColumn(['gpio', 'type']);
        });
    }
};
