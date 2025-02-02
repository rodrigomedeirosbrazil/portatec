<?php

namespace App\Listeners;

use App\Events\MqttMessageEvent;
use App\Events\PlaceDeviceStatusEvent;
use App\Models\Device;
use App\Models\PlaceDevice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

class DeliveryStatusFromMqttListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(MqttMessageEvent $event): void
    {
        if ($this->isLwtEvent($event)) {
            $this->updateDevicesAvailability($event);
            return;
        }

        $this->updateDevicesStatus($event);
    }

    public function isLwtEvent(MqttMessageEvent $event): bool
    {
        return Str::endsWith($event->topic, '/LWT');
    }

    public function updateDevicesStatus(MqttMessageEvent $event): void
    {
        Device::query()
            ->where('topic', $event->topic)
            ->with('placeDevices.place')
            ->cursor()
            ->each(function (Device $device) use ($event) {
                $payload = $event->message;
                if ($device->json_attribute) {
                    $payload = data_get(json_decode($event->message), $device->json_attribute);
                }

                $device->status = $payload;
                $device->save();

                $device->placeDevices->each(fn (PlaceDevice $placeDevice) =>
                    PlaceDeviceStatusEvent::dispatch(
                        $placeDevice->place_id,
                        $device->id,
                        $payload
                    )
                );
            });
    }

    public function updateDevicesAvailability(MqttMessageEvent $event): void
    {
        Device::query()
            ->where('availability_topic', $event->topic)
            ->with('placeDevices.place')
            ->cursor()
            ->each(function (Device $device) use ($event) {
                $status = $event->message;

                $device->is_available = $status === $device->availability_payload_on;
                $device->save();

                $device->placeDevices->each(fn (PlaceDevice $placeDevice) =>
                    PlaceDeviceStatusEvent::dispatch(
                        $placeDevice->place_id,
                        $device->id,
                        $device->is_available,
                        $status
                    )
                );
            });
    }
}
