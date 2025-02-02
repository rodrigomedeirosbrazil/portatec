<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('availability_topic')->nullable()->after('command_topic');
            $table->string('availability_payload_on')->nullable()->after('availability_topic');
            $table->boolean('is_available')->default(true)->after('availability_payload_on');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('availability_topic');
            $table->dropColumn('availability_payload_on');
            $table->dropColumn('is_available');
        });
    }
};
