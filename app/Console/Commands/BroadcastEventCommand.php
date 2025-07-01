<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\DevicePulseEvent;
use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BroadcastEventCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:broadcast-pulse {chip_id? : O chip_id do dispositivo (opcional)} {--all : Enviar pulse para todos os dispositivos} {--data= : Dados JSON personalizados para o pulse}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia evento pulse para o canal do dispositivo via broadcast';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chipId = $this->argument('chip_id');
        $sendToAll = $this->option('all');
        $customData = $this->option('data');

        // Parse custom data if provided
        $pulseData = [];
        if ($customData) {
            try {
                $pulseData = json_decode($customData, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->error("Dados JSON inválidos: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        if ($sendToAll) {
            return $this->sendPulseToAllDevices($pulseData);
        }

        if (! $chipId) {
            $this->error('É necessário fornecer um chip_id ou usar a opção --all');

            return self::FAILURE;
        }

        return $this->sendPulseToDevice($chipId, $pulseData);
    }

    private function sendPulseToDevice(string $chipId, array $pulseData = []): int
    {
        try {
            // Verificar se o dispositivo existe
            $device = Device::where('chip_id', $chipId)->first();

            if (! $device) {
                $this->warn("Dispositivo com chip_id '{$chipId}' não encontrado no banco de dados, mas o pulse será enviado mesmo assim.");
            }

            // Enviar o evento pulse
            broadcast(new DevicePulseEvent($chipId, $pulseData));

            $this->info("✅ Pulse enviado com sucesso para o dispositivo: {$chipId}");

            Log::info('Pulse enviado via comando', [
                'chip_id' => $chipId,
                'pulse_data' => $pulseData,
                'device_exists' => $device !== null,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Erro ao enviar pulse: {$e->getMessage()}");

            Log::error('Erro ao enviar pulse via comando', [
                'chip_id' => $chipId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    private function sendPulseToAllDevices(array $pulseData = []): int
    {
        try {
            $devices = Device::whereNotNull('chip_id')->get();

            if ($devices->isEmpty()) {
                $this->warn('Nenhum dispositivo com chip_id encontrado.');

                return self::SUCCESS;
            }

            $this->info("Enviando pulse para {$devices->count()} dispositivos...");

            $successCount = 0;
            $failCount = 0;

            foreach ($devices as $device) {
                try {
                    broadcast(new DevicePulseEvent($device->chip_id, $pulseData));
                    $this->line("✅ Pulse enviado para: {$device->chip_id} ({$device->name})");
                    $successCount++;
                } catch (\Exception $e) {
                    $this->line("❌ Erro ao enviar para {$device->chip_id}: {$e->getMessage()}");
                    $failCount++;
                }
            }

            $this->info("Pulse enviado para {$successCount} dispositivos com sucesso. {$failCount} falharam.");

            Log::info('Pulse enviado para múltiplos dispositivos', [
                'total_devices' => $devices->count(),
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'pulse_data' => $pulseData,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Erro ao enviar pulse para todos os dispositivos: {$e->getMessage()}");

            Log::error('Erro ao enviar pulse para múltiplos dispositivos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
