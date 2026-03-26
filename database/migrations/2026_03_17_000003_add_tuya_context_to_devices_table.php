<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('integration_id')->nullable()->after('place_id')->constrained()->nullOnDelete();
            $table->string('tuya_category')->nullable()->after('firmware_version');
            $table->string('tuya_product_id')->nullable()->after('tuya_category');
            $table->string('tuya_product_name')->nullable()->after('tuya_product_id');
            $table->text('tuya_icon')->nullable()->after('tuya_product_name');
            $table->boolean('tuya_online')->nullable()->after('tuya_icon');
            $table->json('tuya_status_payload')->nullable()->after('tuya_online');

            $table->index(['integration_id']);
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex(['integration_id']);
            $table->dropConstrainedForeignId('integration_id');
            $table->dropColumn([
                'tuya_category',
                'tuya_product_id',
                'tuya_product_name',
                'tuya_icon',
                'tuya_online',
                'tuya_status_payload',
            ]);
        });
    }
};
