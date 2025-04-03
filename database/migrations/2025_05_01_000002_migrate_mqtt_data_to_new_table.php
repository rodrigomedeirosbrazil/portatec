<?php

use App\Enums\DeviceRelatedTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration creates a bidirectional relationship between devices and mqtt_devices:
     * 1. The mqtt_devices.device_id points to the original device
     * 2. The devices.device_related_id points to the mqtt_device
     */
    public function up(): void
    {
        // Migrar dados de devices para mqtt_devices
        $devices = DB::table('devices')->get();

        foreach ($devices as $device) {
            // Só migra se tiver pelo menos o campo 'topic' preenchido
            if (!empty($device->topic)) {
                // Insere na tabela mqtt_devices
                $mqttDeviceId = DB::table('mqtt_devices')->insertGetId([
                    'device_id' => $device->id, // Set the reference back to the original device
                    'topic' => $device->topic,
                    'command_topic' => $device->command_topic,
                    'payload_on' => $device->payload_on,
                    'payload_off' => $device->payload_off,
                    'availability_topic' => $device->availability_topic ?? null,
                    'availability_payload_on' => $device->availability_payload_on ?? null,
                    'is_available' => $device->is_available ?? true,
                    'json_attribute' => $device->json_attribute ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Atualiza o device para apontar para o novo mqtt_device
                DB::table('devices')
                    ->where('id', $device->id)
                    ->update([
                        'device_type' => DeviceRelatedTypeEnum::Mqtt->value,
                        'device_related_id' => $mqttDeviceId,
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recuperar os dados MQTT para a tabela devices
        $devices = DB::table('devices')
            ->where('device_type', DeviceRelatedTypeEnum::Mqtt->value)
            ->whereNotNull('device_related_id')
            ->get();

        foreach ($devices as $device) {
            $mqttDevice = DB::table('mqtt_devices')
                ->where('id', $device->device_related_id)
                ->first();

            if ($mqttDevice) {
                DB::table('devices')
                    ->where('id', $device->id)
                    ->update([
                        'topic' => $mqttDevice->topic,
                        'command_topic' => $mqttDevice->command_topic,
                        'payload_on' => $mqttDevice->payload_on,
                        'payload_off' => $mqttDevice->payload_off,
                        'availability_topic' => $mqttDevice->availability_topic,
                        'availability_payload_on' => $mqttDevice->availability_payload_on,
                        'is_available' => $mqttDevice->is_available,
                        'json_attribute' => $mqttDevice->json_attribute,
                        'device_type' => null,
                        'device_related_id' => null,
                    ]);
            }
        }
    }
};
