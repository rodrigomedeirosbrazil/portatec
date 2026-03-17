<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->string('tuya_user_code')->nullable()->after('user_id');
            $table->text('tuya_access_token')->nullable()->after('tuya_user_code');
            $table->text('tuya_refresh_token')->nullable()->after('tuya_access_token');
            $table->dateTime('tuya_token_expires_at')->nullable()->after('tuya_refresh_token');
            $table->string('tuya_uid')->nullable()->after('tuya_token_expires_at');
            $table->string('tuya_endpoint')->nullable()->after('tuya_uid');
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn([
                'tuya_user_code',
                'tuya_access_token',
                'tuya_refresh_token',
                'tuya_token_expires_at',
                'tuya_uid',
                'tuya_endpoint',
            ]);
        });
    }
};
