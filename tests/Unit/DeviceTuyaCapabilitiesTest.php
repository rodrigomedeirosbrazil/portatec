<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTuyaCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tuya_device_without_category_is_not_a_lock(): void
    {
        $device = new Device(['brand' => DeviceBrandEnum::Tuya, 'tuya_category' => null]);

        $this->assertFalse($device->isTuyaLock());
    }

    public function test_a_lock_without_the_dp_does_not_support_temporary_passwords(): void
    {
        $device = new Device([
            'brand' => DeviceBrandEnum::Tuya,
            'tuya_category' => 'jtmspro',
            'tuya_functions' => ['unlock_method_create', 'lock_motor_state'],
        ]);

        $this->assertTrue($device->isTuyaLock());
        $this->assertFalse($device->supportsTuyaTemporaryPassword());
    }

    public function test_a_lock_with_the_dp_supports_temporary_passwords(): void
    {
        $device = new Device([
            'brand' => DeviceBrandEnum::Tuya,
            'tuya_category' => 'ms',
            'tuya_functions' => ['temporary_password_creat', 'temporary_password_delete'],
        ]);

        $this->assertTrue($device->supportsTuyaTemporaryPassword());
    }

    public function test_only_devices_that_can_receive_pins_support_place_access_codes(): void
    {
        $portatec = new Device(['brand' => DeviceBrandEnum::Portatec]);
        $tuyaLock = new Device([
            'brand' => DeviceBrandEnum::Tuya,
            'tuya_category' => 'ms',
            'tuya_functions' => ['temporary_password_creat'],
        ]);
        $tuyaSwitch = new Device(['brand' => DeviceBrandEnum::Tuya, 'tuya_category' => 'kg']);

        $this->assertTrue($portatec->supportsPlaceAccessCodes());
        $this->assertTrue($tuyaLock->supportsPlaceAccessCodes());
        $this->assertFalse($tuyaSwitch->supportsPlaceAccessCodes());
    }
}
