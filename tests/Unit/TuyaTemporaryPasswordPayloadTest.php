<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tuya\TuyaIntegrationService;
use Tests\TestCase;

class TuyaTemporaryPasswordPayloadTest extends TestCase
{
    public function test_create_payload_has_21_bytes_in_the_documented_layout(): void
    {
        $service = new TuyaIntegrationService;
        $method = new \ReflectionMethod($service, 'buildCreatePayload');

        $value = $method->invoke($service, 0x1234, 0xABCD, '123456', 1770000000, 1770086400);
        $bytes = base64_decode($value, true);

        $this->assertSame(21, strlen($bytes));
        $this->assertSame(0x1234, unpack('n', substr($bytes, 0, 2))[1]);   // tuya serial
        $this->assertSame(0xABCD, unpack('n', substr($bytes, 2, 2))[1]);   // server serial
        $this->assertSame(0x0000, unpack('n', substr($bytes, 4, 2))[1]);   // lock manufacturer id
        $this->assertSame(1770000000, unpack('N', substr($bytes, 6, 4))[1]);  // start
        $this->assertSame(1770086400, unpack('N', substr($bytes, 10, 4))[1]); // end
        $this->assertSame("\x00", substr($bytes, 14, 1));                  // não é one-time
        $this->assertSame('123456', substr($bytes, 15, 6));                // PIN ASCII
    }

    public function test_delete_payload_has_6_bytes(): void
    {
        $service = new TuyaIntegrationService;
        $method = new \ReflectionMethod($service, 'buildDeletePayload');

        $bytes = base64_decode($method->invoke($service, 0x1234, 0xABCD), true);

        $this->assertSame(6, strlen($bytes));
        $this->assertSame(0x1234, unpack('n', substr($bytes, 0, 2))[1]);
        $this->assertSame(0xABCD, unpack('n', substr($bytes, 2, 2))[1]);
        $this->assertSame(0x0000, unpack('n', substr($bytes, 4, 2))[1]);
    }
}
