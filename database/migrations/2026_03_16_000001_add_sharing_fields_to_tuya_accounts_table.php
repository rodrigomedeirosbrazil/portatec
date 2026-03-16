<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tuya_accounts', function (Blueprint $table) {
            $table->string('user_code')->nullable()->after('user_id');
            $table->json('token_info')->nullable()->after('refresh_token');
            $table->string('terminal_id')->nullable()->after('token_info');
            $table->string('endpoint')->nullable()->after('terminal_id');
        });
    }

    public function down(): void
    {
        Schema::table('tuya_accounts', function (Blueprint $table) {
            $table->dropColumn(['user_code', 'token_info', 'terminal_id', 'endpoint']);
        });
    }
};
