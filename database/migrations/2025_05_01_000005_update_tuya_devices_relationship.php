<?php

use App\Enums\DeviceRelatedTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tuya_devices', 'device_id')) {
            $tuyaDevices = DB::table('tuya_devices')
                ->whereNotNull('device_id')
                ->get();

            foreach ($tuyaDevices as $tuyaDevice) {
                DB::table('devices')
                    ->where('id', $tuyaDevice->device_id)
                    ->update([
                        'device_type' => DeviceRelatedTypeEnum::Tuya->value,
                        'device_related_id' => $tuyaDevice->id,
                    ]);
            }

            Schema::table('tuya_devices', function ($table) {
                $table->dropForeign(['device_id']);
                $table->dropColumn('device_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('tuya_devices', 'device_id')) {
            Schema::table('tuya_devices', function ($table) {
                $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            });

            $devices = DB::table('devices')
                ->where('device_type', DeviceRelatedTypeEnum::Tuya->value)
                ->whereNotNull('device_related_id')
                ->get();

            foreach ($devices as $device) {
                DB::table('tuya_devices')
                    ->where('id', $device->device_related_id)
                    ->update([
                        'device_id' => $device->id,
                    ]);

                DB::table('devices')
                    ->where('id', $device->id)
                    ->update([
                        'device_type' => null,
                        'device_related_id' => null,
                    ]);
            }
        }
    }
};
