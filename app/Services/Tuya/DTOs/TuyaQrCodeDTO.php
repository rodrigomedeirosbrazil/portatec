<?php

declare(strict_types=1);

namespace App\Services\Tuya\DTOs;

class TuyaQrCodeDTO
{
    public function __construct(
        /** Token que compõe o conteúdo do QR: tuyaSmart--qrLogin?token={qr_code} */
        public readonly string $qrCode,
        /** URL completa pronta para gerar o QR */
        public readonly string $qrUrl,
        /** Timestamp Unix de expiração do QR */
        public readonly int $expireTime,
    ) {}
}
