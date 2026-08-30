<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Integration;
use App\Services\Tuya\TuyaMqttService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Throwable;

/**
 * Assina o broker MQTT da Tuya e aplica os eventos recebidos.
 *
 * O broker é dinâmico — host, porta e credenciais vêm de /v1.0/m/life/ha/access/config —
 * então este comando não usa a facade MQTT (que serve o mosquitto local do mqtt:subscribe).
 *
 * As credenciais expiram em `expireTime` segundos (~2h). O comando encerra ao fim do loop
 * e o supervisord o reinicia, obtendo credenciais novas.
 */
class TuyaSubscribeCommand extends Command
{
    protected $signature = 'tuya:subscribe
                            {integration? : ID da integração Tuya (padrão: a mais recente)}
                            {--once : Processa uma iteração do loop e sai}';

    protected $description = 'Assina o broker MQTT da Tuya e aplica os eventos dos dispositivos.';

    /**
     * Segundos de espera antes de sair quando ainda não há o que assinar. Sob supervisord
     * o autorestart transforma isso num poller barato: assim que uma integração Tuya for
     * conectada, o próximo ciclo já a encontra. Sair com erro marcaria o programa como
     * FATAL e ele não voltaria sozinho.
     */
    private const IDLE_BACKOFF_SECONDS = 60;

    public function handle(TuyaMqttService $service): int
    {
        $integration = $this->resolveIntegration();

        if (! $integration instanceof Integration) {
            return $this->idle('Nenhuma integração Tuya conectada — nada a assinar.');
        }

        try {
            $config = $service->config($integration);
        } catch (Throwable $exception) {
            report($exception);

            return $this->idle('Falha ao obter a configuração MQTT da Tuya: '.$exception->getMessage());
        }

        $url = (string) ($config['url'] ?? '');
        $parts = parse_url($url);

        if ($url === '' || ! is_array($parts) || ! isset($parts['host'])) {
            return $this->idle('A Tuya não devolveu uma URL de MQTT utilizável.');
        }

        $topics = $service->topicsFor($integration, $config);

        if ($topics === []) {
            return $this->idle('Nenhum tópico para assinar — a integração não tem dispositivos importados.');
        }

        $client = new MqttClient(
            $parts['host'],
            (int) ($parts['port'] ?? 8883),
            (string) $config['clientId'],
        );

        $settings = (new ConnectionSettings)
            ->setUsername((string) $config['username'])
            ->setPassword((string) $config['password'])
            ->setUseTls(($parts['scheme'] ?? '') === 'ssl');

        $client->connect($settings, true);

        foreach ($topics as $topic) {
            $client->subscribe($topic, function (string $topic, string $message) use ($service): void {
                $payload = json_decode($message, true);

                if (! is_array($payload)) {
                    Log::warning('[Tuya MQTT] payload não é JSON válido', [
                        'topic' => $topic,
                        'message' => substr($message, 0, 200),
                    ]);

                    return;
                }

                $service->handleMessage($payload);
            });
        }

        Log::info('[Tuya MQTT] subscriber iniciado', [
            'integration_id' => $integration->id,
            'host' => $parts['host'],
            'topics' => $topics,
            'expire_time' => $config['expireTime'] ?? null,
        ]);

        $this->info('Assinando '.count($topics).' tópico(s) em '.$parts['host'].'. Ctrl+C para sair.');

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, fn () => $client->interrupt());
        pcntl_signal(SIGTERM, fn () => $client->interrupt());

        $client->loop(! $this->option('once'));
        $client->disconnect();

        return self::SUCCESS;
    }

    /**
     * Nada a fazer agora: registra o motivo, espera e sai com sucesso, para o supervisord
     * reiniciar sem marcar o programa como FATAL.
     */
    private function idle(string $reason): int
    {
        Log::info('[Tuya MQTT] subscriber ocioso', ['reason' => $reason]);
        $this->warn($reason);

        if (! $this->option('once')) {
            sleep(self::IDLE_BACKOFF_SECONDS);
        }

        return self::SUCCESS;
    }

    private function resolveIntegration(): ?Integration
    {
        $query = Integration::query()
            ->whereHas('platform', fn ($query) => $query->where('slug', 'tuya'))
            ->whereNotNull('tuya_refresh_token');

        $id = $this->argument('integration');

        return $id === null
            ? $query->latest('updated_at')->first()
            : $query->whereKey($id)->first();
    }
}
