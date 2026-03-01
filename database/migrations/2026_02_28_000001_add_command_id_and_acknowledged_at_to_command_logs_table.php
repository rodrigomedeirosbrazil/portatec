<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('command_logs', function (Blueprint $table) {
            $table->string('command_id')->nullable()->after('id')->index();
            $table->timestamp('acknowledged_at')->nullable()->after('command_payload');
        });
    }

    public function down(): void
    {
        Schema::table('command_logs', function (Blueprint $table) {
            $table->dropIndex(['command_id']);
            $table->dropColumn(['command_id', 'acknowledged_at']);
        });
    }
};
