<?php

use App\Services\Tuya\TuyaStatusPayload;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove de `tuya_status_payload` os DPs sensíveis já gravados — o `ble_unlock_check` carrega em
 * claro o código que abre a fechadura por Bluetooth (AGENTS.md §11.6.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('devices')
            ->where('brand', 'tuya')
            ->whereNotNull('tuya_status_payload')
            ->orderBy('id')
            ->each(function (object $row): void {
                $payload = json_decode((string) $row->tuya_status_payload, true);

                if (! is_array($payload)) {
                    return;
                }

                DB::table('devices')
                    ->where('id', $row->id)
                    ->update(['tuya_status_payload' => json_encode(TuyaStatusPayload::merge($payload, []))]);
            });
    }

    /** Nada a restaurar: o dado removido não deveria ter sido gravado. */
    public function down(): void {}
};
