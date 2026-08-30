# SPEC — Conclusão da integração Tuya (PR #24)

> **Documento temporário — remover antes do merge da PR #24.** Vive aqui só enquanto a
> integração está sendo concluída; o conteúdo duradouro vai para o `AGENTS.md` §11 (Task 15).

> **Para quem for executar:** as tarefas estão em passos de 2–5 min no formato TDD
> (escreve teste → vê falhar → implementa → vê passar → commita). Use checkbox `- [ ]`.
> Todo comando roda via `./vendor/bin/sail`.

**Branch:** `feature/tuya-new-2`, já mergeada com `origin/main` (merge `849d783`, style `7bcff22`).
34 testes passando, Pint limpo.

---

## Objetivo

Fazer o PortaTec criar, atualizar e remover PINs temporários em fechaduras Tuya usando o
mecanismo *device sharing* (mesmo do Home Assistant, sem conta de developer), com confirmação
real de sucesso e recebimento de eventos do dispositivo.

## Arquitetura

Três camadas, hoje todas amontoadas em `TuyaQrAuthService`:

1. **Login QR** (sem assinatura) — fica em `TuyaQrAuthService`.
2. **CustomerApi** (AES-128-GCM + `X-sign` + refresh de token) — sai para um
   `TuyaCustomerApiClient` novo, que é o único ponto que fala HTTP autenticado com a Tuya.
3. **Domínio Tuya** (homes, devices, specifications, comandos DP) — fica em
   `TuyaIntegrationService`, consumindo o client.

Mais um canal de entrada: **MQTT da própria Tuya** (`/v1.0/m/life/ha/access/config`), que
substitui a dúvida "webhook ou polling?" do plano original.

**Stack:** Laravel 11 · PHP 8.4 · `Illuminate\Support\Facades\Http` · `php-mqtt/laravel-client`
· PHPUnit 11 · SQLite em memória nos testes.

## Referência normativa

O port é do `tuya-device-sharing-sdk` (Python, MIT). Sempre que houver dúvida de protocolo,
a resposta está no fonte, não em documentação:

```bash
curl -sL -o /tmp/tuya-sdk.tar.gz https://github.com/tuya/tuya-device-sharing-sdk/archive/refs/heads/main.tar.gz
tar xzf /tmp/tuya-sdk.tar.gz -C /tmp
# /tmp/tuya-device-sharing-sdk-main/tuya_sharing/{user,customerapi,device,home,mq,manager}.py
```

Comparação já feita (não precisa refazer): `_secret_generating`, `_restful_sign`,
`_aes_gcm_encrypt` e `_aex_gcm_decrypt` **estão corretos** no PHP atual. Os defeitos são os
listados abaixo.

---

## Defeitos confirmados (o que esta SPEC resolve)

| # | Defeito | Onde | Efeito |
|---|---|---|---|
| D1 | Sem refresh de token | não existe | `sign invalid` ~2h após o QR — `hash_key` deriva do `refresh_token` |
| D2 | Sucesso escalar indistinguível de falha | `TuyaQrAuthService::customerRequest()` | PIN criado é gravado como `status: failed` |
| D3 | `schema=tuyaSmart` no login | `TuyaQrAuthService::generateQrCode()` | HA usa `haauthorize` |
| D4 | `$method` ignorado (verbo vem de "tem body?") | `TuyaQrAuthService::customerRequest()` | POST sem body vira GET |
| D5 | `terminal_id` não capturado | `pollLogin()` / migration | impossível desconectar direito |
| D6 | Nunca lê `/specifications` | — | manda DP às cegas; Zigbee e Wi-Fi usam DPs diferentes |
| D7 | `isTuyaLock()` aceita `tuya_category === null` | `Device.php:139` | manda PIN para qualquer device Tuya |
| D8 | Resultado do DP é assíncrono | — | HTTP só confirma aceite, não execução |
| D9 | `Log::debug` com token e assinatura | `customerRequest()` | vaza credencial no log |
| D10 | `:include-unassigned` não existe no componente | `place-select` | filtro "Sem local" não renderiza |

---

## Estrutura de arquivos

**Criar**
- `app/Exceptions/TuyaApiException.php` — erro de protocolo/API Tuya
- `app/Services/Tuya/TuyaCustomerApiClient.php` — camada 2 (cripto, assinatura, refresh)
- `app/Services/Tuya/TuyaMqttService.php` — config e interpretação das mensagens MQTT
- `app/Console/Commands/TuyaSubscribeCommand.php` — `tuya:subscribe`
- `tests/Unit/TuyaCustomerApiClientTest.php`
- `tests/Unit/TuyaTemporaryPasswordPayloadTest.php`
- `tests/Unit/TuyaMqttServiceTest.php`
- `tests/Feature/TuyaTokenRefreshTest.php`

**Modificar**
- `app/Services/Tuya/TuyaQrAuthService.php` — some tudo menos o login QR
- `app/Services/Tuya/TuyaIntegrationService.php` — passa a usar o client
- `app/Services/Tuya/DTOs/TuyaTokenDTO.php` — ganha `terminalId` e `serverTime`
- `app/Models/Device.php` — `isTuyaLock()`, `supportsTuyaTemporaryPassword()`, cast novo
- `app/Models/Integration.php` — `tuya_terminal_id`
- `app/Livewire/Integrations/TuyaConnect.php` — persistir `terminal_id`, expiração pelo `t`
- `app/Services/AccessCodeSyncService.php` — limpar logs, usar a capability nova
- `app/View/Components/PlaceSelect.php` + `resources/views/components/place-select.blade.php`
- `database/migrations/2026_03_17_000001_add_tuya_fields_to_integrations_table.php` — **editar**
- `database/migrations/2026_03_17_000003_add_tuya_context_to_devices_table.php` — **editar**
- `AGENTS.md` §11

> **Regra 8 do AGENTS.md:** as migrations `2026_03_17_*` ainda não foram mergeadas na `main`.
> Edite as originais — **não crie migration nova** para alterá-las.

**Remover**
- `app/Services/Tuya/Client.php`, `app/Services/Tuya/TuyaService.php` (legado OpenAPI)
- `app/Services/Tuya/DTOs/TuyaAuthenticationDTO.php`, `TuyaTicketDTO.php`
- `config/tuya.php`
- `tests/Feature/TuyaClientTest.php`

---

# Fase 0 — Higiene

### Task 1: Remover o legado OpenAPI

**Files:**
- Delete: `app/Services/Tuya/Client.php`, `app/Services/Tuya/TuyaService.php`,
  `app/Services/Tuya/DTOs/TuyaAuthenticationDTO.php`, `app/Services/Tuya/DTOs/TuyaTicketDTO.php`,
  `config/tuya.php`, `tests/Feature/TuyaClientTest.php`

Verificado: essas classes só são referenciadas por `TuyaClientTest.php`. Nada em `app/` usa.

- [ ] **Passo 1: Confirmar que não há uso**

```bash
./vendor/bin/sail exec -T laravel.test grep -rn "Tuya\\\\Client\|TuyaService\|config('tuya" app/ routes/ database/
```

Esperado: nenhuma saída.

- [ ] **Passo 2: Remover**

```bash
rm app/Services/Tuya/Client.php app/Services/Tuya/TuyaService.php \
   app/Services/Tuya/DTOs/TuyaAuthenticationDTO.php app/Services/Tuya/DTOs/TuyaTicketDTO.php \
   config/tuya.php tests/Feature/TuyaClientTest.php
```

- [ ] **Passo 3: Rodar a suíte**

```bash
./vendor/bin/sail test
```

Esperado: `Tests: 32 passed` (caem os 2 testes do `TuyaClientTest`).

- [ ] **Passo 4: Commit**

```bash
git add -A
git commit -m "chore(tuya): remove legacy OpenAPI client and config"
```

> As variáveis `TUYA_CLIENT_ID`, `TUYA_CLIENT_SECRET` e `TUYA_BASE_URL` ficam órfãs no `.env`.
> **Não edite o `.env`** — remova você mesmo depois, e tire também do `.env.example` se estiverem lá.

---

### Task 2: Remover diagnósticos e código morto

**Files:**
- Modify: `app/Services/Tuya/TuyaIntegrationService.php`
- Modify: `app/Services/AccessCodeSyncService.php`

- [ ] **Passo 1: Apagar os métodos de diagnóstico**

Em `TuyaIntegrationService.php`, remover integralmente `testSendCommand()` e
`probeCommandEndpoint()` (e o `use Illuminate\Support\Facades\Log;` se ficar sem uso).
Foram sondas de tinker para o `sign invalid` — o path correto (`/v1.1/m/thing/{id}/commands`)
está confirmado no SDK (`device.py:180`).

- [ ] **Passo 2: Apagar o método morto do sync**

Em `AccessCodeSyncService.php`, remover `buildRemoteAccessCodeName()` — nenhum chamador.

- [ ] **Passo 3: Reduzir os logs de depuração do sync**

Remover os blocos `Log::info('[Tuya sync] Dispositivos do place ...')` e
`Log::debug('[Tuya sync] Dispositivo candidato', ...)` de `devicesForPlaceAccessCode()` e
`syncNewAccessCode()`. Eles rodam em toda criação de PIN e poluem até a saída dos testes.
Manter os `Log::info` de "PIN adicionado" / `Log::error` de falha.

- [ ] **Passo 4: Rodar Pint e a suíte**

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test
```

Esperado: `PASS` no Pint e `Tests: 32 passed`.

- [ ] **Passo 5: Commit**

```bash
git add -A
git commit -m "chore(tuya): drop diagnostic probes and noisy sync logs"
```

---

# Fase 1 — Protocolo CustomerApi correto

Esta é a fase que destrava o `sign invalid`. Sozinha ela já deve fazer o envio de comando
funcionar de forma estável.

### Task 3: Exceção de API

**Files:**
- Create: `app/Exceptions/TuyaApiException.php`

- [ ] **Passo 1: Criar a exceção**

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class TuyaApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $tuyaCode = null,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }

    public static function http(string $path, int $status, string $body): self
    {
        return new self(
            "Tuya respondeu HTTP {$status} em {$path}: ".substr($body, 0, 200),
            httpStatus: $status,
        );
    }

    public static function api(string $path, ?string $code, ?string $message): self
    {
        return new self(
            "Tuya recusou {$path}: [{$code}] ".($message ?? 'sem mensagem'),
            tuyaCode: $code,
        );
    }
}
```

- [ ] **Passo 2: Commit**

```bash
git add app/Exceptions/TuyaApiException.php
git commit -m "feat(tuya): add TuyaApiException"
```

---

### Task 4: `TuyaCustomerApiClient` — camada 2 isolada

Contrato novo, que corrige **D2** e **D4**:
- devolve o `result` já decifrado e decodificado, seja array, bool ou string;
- **lança** `TuyaApiException` em qualquer falha (HTTP ou `success: false`);
- respeita o verbo HTTP recebido.

**Files:**
- Create: `app/Services/Tuya/TuyaCustomerApiClient.php`
- Test: `tests/Unit/TuyaCustomerApiClientTest.php`

- [ ] **Passo 1: Escrever o teste que falha**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\TuyaApiException;
use App\Models\Integration;
use App\Models\Platform;
use App\Models\User;
use App\Services\Tuya\TuyaCustomerApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TuyaCustomerApiClientTest extends TestCase
{
    use RefreshDatabase;

    /** O repositório não usa factories (só UserFactory) — crie os models na mão. */
    private function integration(array $overrides = []): Integration
    {
        $platform = Platform::create(['name' => 'Tuya SmartLife', 'slug' => 'tuya']);
        $user = User::factory()->create();

        return Integration::create(array_merge([
            'platform_id' => $platform->id,
            'user_id' => $user->id,
            'tuya_access_token' => 'access-token',
            'tuya_refresh_token' => 'refresh-token',
            'tuya_endpoint' => 'apigw.tuyaus.com',
            'tuya_token_expires_at' => now()->addHour(),
        ], $overrides));
    }

    /** Cifra um payload do mesmo jeito que o servidor Tuya faria, para o fake. */
    private function encryptAsServer(string $plain, string $secret): string
    {
        $nonce = 'ABCDEFGHJKMN';
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-128-gcm', $secret, OPENSSL_RAW_DATA, $nonce, $tag);

        return base64_encode($nonce).base64_encode($cipher.$tag);
    }

    public function test_it_uses_the_regional_endpoint_and_signs_the_request(): void
    {
        Http::fake(['apigw.tuyaus.com/*' => Http::response(['success' => true, 'result' => null])]);

        (new TuyaCustomerApiClient)->get($this->integration(), '/v1.0/m/life/users/homes');

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://apigw.tuyaus.com/v1.0/m/life/users/homes')
                && $request->hasHeader('X-appKey', 'HA_3y9q4ak7g4ephrvke')
                && $request->hasHeader('X-token', 'access-token')
                && $request->header('X-sign')[0] !== ''
                && $request->method() === 'GET';
        });
    }

    public function test_it_posts_when_the_method_is_post_even_without_body(): void
    {
        Http::fake(['apigw.tuyaus.com/*' => Http::response(['success' => true, 'result' => null])]);

        (new TuyaCustomerApiClient)->post($this->integration(), '/v1.0/m/life/ping');

        Http::assertSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_it_decodes_boolean_result(): void
    {
        $client = new TuyaCustomerApiClient;
        $secret = '0123456789abcdef';

        $decoded = $this->invokeDecode($client, $this->encryptAsServer('true', $secret), $secret);

        $this->assertTrue($decoded);
    }

    public function test_it_decodes_array_result(): void
    {
        $client = new TuyaCustomerApiClient;
        $secret = '0123456789abcdef';

        $decoded = $this->invokeDecode($client, $this->encryptAsServer('[{"id":"a"}]', $secret), $secret);

        $this->assertSame([['id' => 'a']], $decoded);
    }

    public function test_it_throws_when_tuya_reports_failure(): void
    {
        Http::fake(['apigw.tuyaus.com/*' => Http::response([
            'success' => false,
            'code' => 1004,
            'msg' => 'sign invalid',
        ])]);

        $this->expectException(TuyaApiException::class);
        $this->expectExceptionMessageMatches('/sign invalid/');

        (new TuyaCustomerApiClient)->get($this->integration(), '/v1.0/m/life/users/homes');
    }

    public function test_it_throws_on_http_error(): void
    {
        Http::fake(['apigw.tuyaus.com/*' => Http::response('boom', 500)]);

        $this->expectException(TuyaApiException::class);

        (new TuyaCustomerApiClient)->get($this->integration(), '/v1.0/m/life/users/homes');
    }

    private function invokeDecode(TuyaCustomerApiClient $client, string $cipher, string $secret): mixed
    {
        $method = new \ReflectionMethod($client, 'decodeResult');

        return $method->invoke($client, $cipher, $secret);
    }
}
```

> **Nota:** `decodeResult()` é privado e é testado por reflexão de propósito — o `secret`
> real depende de um `rid` aleatório gerado dentro do client, então não dá para montar um
> `Http::fake()` com o `result` já cifrado sem primeiro capturar esse `rid`.

- [ ] **Passo 2: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=TuyaCustomerApiClientTest
```

Esperado: FAIL — `Class "App\Services\Tuya\TuyaCustomerApiClient" not found`.

> **Convenção do repo:** só existe `database/factories/UserFactory.php`. Os testes montam os
> demais models com `Model::create()` ou `DB::table()->insertGetId()` (veja
> `tests/Unit/AccessCodeSyncServiceTest.php`). **Não crie factories novas** — siga o padrão.

- [ ] **Passo 3: Implementar o client**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tuya;

use App\Exceptions\TuyaApiException;
use App\Models\Integration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Camada CustomerApi do device sharing da Tuya.
 * Port de tuya_sharing/customerapi.py (CustomerApi.__request).
 */
class TuyaCustomerApiClient
{
    public const CLIENT_ID = 'HA_3y9q4ak7g4ephrvke';

    public const BASE_URL = 'https://apigw.iotbing.com';

    private const NONCE_ALPHABET = 'ABCDEFGHJKMNPQRSTWXYZabcdefhijkmnprstwxyz2345678';

    /** Evita recursão infinita quando o próprio refresh dispara um request. */
    private bool $refreshing = false;

    /** @param array<string, mixed>|null $params */
    public function get(Integration $integration, string $path, ?array $params = null): mixed
    {
        return $this->request($integration, 'GET', $path, $params, null);
    }

    /**
     * @param  array<string, mixed>|null  $params
     * @param  array<string, mixed>|null  $body
     */
    public function post(Integration $integration, string $path, ?array $params = null, ?array $body = null): mixed
    {
        return $this->request($integration, 'POST', $path, $params, $body);
    }

    /**
     * @param  array<string, mixed>|null  $params
     * @param  array<string, mixed>|null  $body
     *
     * @throws TuyaApiException
     */
    private function request(
        Integration $integration,
        string $method,
        string $path,
        ?array $params,
        ?array $body,
    ): mixed {
        $this->refreshTokenIfNeeded($integration);

        $rid = (string) Str::uuid();
        $sid = '';
        $hashKey = md5($rid.(string) $integration->tuya_refresh_token);
        $secret = substr(hash_hmac('sha256', $hashKey, $rid), 0, 16);
        $timestamp = (string) (int) (microtime(true) * 1000);

        $queryEncdata = ($params === null || $params === [])
            ? null
            : $this->encrypt($this->toJson($params), $secret);

        $bodyEncdata = ($body === null || $body === [])
            ? null
            : $this->encrypt($this->toJson($body), $secret);

        $headers = [
            'Accept' => 'application/json',
            'X-appKey' => self::CLIENT_ID,
            'X-requestId' => $rid,
            'X-sid' => $sid,
            'X-time' => $timestamp,
            'X-token' => (string) $integration->tuya_access_token,
        ];

        $headers['X-sign'] = $this->sign($headers, $hashKey, $queryEncdata, $bodyEncdata);

        $url = $this->endpoint($integration).$path;
        if ($queryEncdata !== null) {
            $url .= (str_contains($path, '?') ? '&' : '?').'encdata='.urlencode($queryEncdata);
        }

        $pending = Http::withHeaders($headers)->timeout(15);

        $response = $bodyEncdata === null
            ? $pending->send($method, $url)
            : $pending->withBody(
                $this->toJson(['encdata' => $bodyEncdata]),
                'application/json'
            )->send($method, $url);

        if (! $response->successful()) {
            throw TuyaApiException::http($path, $response->status(), $response->body());
        }

        $data = $response->json();

        if (! is_array($data) || ! ($data['success'] ?? false)) {
            throw TuyaApiException::api(
                $path,
                isset($data['code']) ? (string) $data['code'] : null,
                isset($data['msg']) ? (string) $data['msg'] : null,
            );
        }

        return $this->decodeResult($data['result'] ?? null, $secret);
    }

    /**
     * O SDK renova quando falta menos de 1 min para expirar.
     * O refresh_token rotaciona — e como hash_key = MD5(rid + refresh_token),
     * usar um refresh_token velho produz "sign invalid".
     */
    private function refreshTokenIfNeeded(Integration $integration): void
    {
        if ($this->refreshing) {
            return;
        }

        $expiresAt = $integration->tuya_token_expires_at;
        if ($expiresAt !== null && $expiresAt->subMinute()->isFuture()) {
            return;
        }

        if (blank($integration->tuya_refresh_token)) {
            return;
        }

        $this->refreshing = true;

        try {
            $result = $this->request(
                $integration,
                'GET',
                '/v1.0/m/token/'.$integration->tuya_refresh_token,
                null,
                null,
            );

            if (! is_array($result)) {
                return;
            }

            $integration->forceFill([
                'tuya_access_token' => $result['accessToken'] ?? $integration->tuya_access_token,
                'tuya_refresh_token' => $result['refreshToken'] ?? $integration->tuya_refresh_token,
                'tuya_uid' => $result['uid'] ?? $integration->tuya_uid,
                'tuya_token_expires_at' => now()->addSeconds((int) ($result['expireTime'] ?? 7200)),
            ])->save();
        } finally {
            $this->refreshing = false;
        }
    }

    private function endpoint(Integration $integration): string
    {
        $endpoint = trim((string) $integration->tuya_endpoint);

        if ($endpoint === '') {
            return self::BASE_URL;
        }

        if (! str_starts_with($endpoint, 'http://') && ! str_starts_with($endpoint, 'https://')) {
            $endpoint = 'https://'.$endpoint;
        }

        return rtrim($endpoint, '/');
    }

    /** @param array<string, string> $headers */
    private function sign(array $headers, string $hashKey, ?string $queryEncdata, ?string $bodyEncdata): string
    {
        $parts = [];
        foreach (['X-appKey', 'X-requestId', 'X-sid', 'X-time', 'X-token'] as $key) {
            $value = $headers[$key] ?? '';
            if ($value !== '') {
                $parts[] = $key.'='.$value;
            }
        }

        $signStr = implode('||', $parts).($queryEncdata ?? '').($bodyEncdata ?? '');

        return hash_hmac('sha256', $signStr, $hashKey);
    }

    /** @param array<string, mixed> $value */
    private function toJson(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function randomNonce(int $length = 12): string
    {
        $alphabet = self::NONCE_ALPHABET;
        $max = strlen($alphabet) - 1;
        $nonce = '';
        for ($i = 0; $i < $length; $i++) {
            $nonce .= $alphabet[random_int(0, $max)];
        }

        return $nonce;
    }

    private function encrypt(string $data, string $secret): string
    {
        $nonce = $this->randomNonce();
        $tag = '';
        $cipher = openssl_encrypt($data, 'aes-128-gcm', $secret, OPENSSL_RAW_DATA, $nonce, $tag);

        if ($cipher === false) {
            throw new TuyaApiException('Falha ao cifrar payload Tuya (AES-GCM).');
        }

        return base64_encode($nonce).base64_encode($cipher.$tag);
    }

    /**
     * O `result` vem cifrado como string. Pode decodificar para array, bool, número ou string —
     * todos são resultados válidos e precisam ser distinguíveis de erro (que vira exceção).
     */
    private function decodeResult(mixed $result, string $secret): mixed
    {
        if ($result === null) {
            return null;
        }

        if (! is_string($result)) {
            return $result;
        }

        $raw = base64_decode($result, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new TuyaApiException('Resposta Tuya cifrada com tamanho inválido.');
        }

        $plain = openssl_decrypt(
            substr($raw, 12, -16),
            'aes-128-gcm',
            $secret,
            OPENSSL_RAW_DATA,
            substr($raw, 0, 12),
            substr($raw, -16),
        );

        if ($plain === false) {
            throw new TuyaApiException('Falha ao decifrar resposta Tuya (AES-GCM).');
        }

        try {
            return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $plain;
        }
    }
}
```

- [ ] **Passo 4: Rodar e ver passar**

```bash
./vendor/bin/sail test --filter=TuyaCustomerApiClientTest
```

Esperado: PASS.

- [ ] **Passo 5: Commit**

```bash
./vendor/bin/sail pint
git add -A
git commit -m "feat(tuya): extract TuyaCustomerApiClient with token refresh and strict result handling"
```

---

### Task 5: Refresh de token — teste de integração

Cobre **D1** explicitamente, porque é o defeito mais caro.

**Files:**
- Test: `tests/Feature/TuyaTokenRefreshTest.php`

- [ ] **Passo 1: Escrever o teste**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\Platform;
use App\Models\User;
use App\Services\Tuya\TuyaCustomerApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TuyaTokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function integration(array $overrides): Integration
    {
        $platform = Platform::create(['name' => 'Tuya SmartLife', 'slug' => 'tuya']);

        return Integration::create(array_merge([
            'platform_id' => $platform->id,
            'user_id' => User::factory()->create()->id,
        ], $overrides));
    }

    public function test_it_refreshes_an_expired_token_before_the_real_request(): void
    {
        $integration = $this->integration([
            'tuya_access_token' => 'old-access',
            'tuya_refresh_token' => 'old-refresh',
            'tuya_endpoint' => 'apigw.tuyaus.com',
            'tuya_token_expires_at' => now()->subMinutes(5),
        ]);

        Http::fake([
            'apigw.tuyaus.com/v1.0/m/token/old-refresh' => Http::response([
                'success' => true,
                'result' => [
                    'accessToken' => 'new-access',
                    'refreshToken' => 'new-refresh',
                    'uid' => 'uid-1',
                    'expireTime' => 7200,
                ],
            ]),
            'apigw.tuyaus.com/v1.0/m/life/users/homes' => Http::response([
                'success' => true,
                'result' => null,
            ]),
        ]);

        (new TuyaCustomerApiClient)->get($integration, '/v1.0/m/life/users/homes');

        $integration->refresh();
        $this->assertSame('new-access', $integration->tuya_access_token);
        $this->assertSame('new-refresh', $integration->tuya_refresh_token);
        $this->assertTrue($integration->tuya_token_expires_at->isFuture());

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1.0/m/token/old-refresh'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/users/homes')
            && $request->hasHeader('X-token', 'new-access'));
    }

    public function test_it_does_not_refresh_a_valid_token(): void
    {
        $integration = $this->integration([
            'tuya_access_token' => 'access',
            'tuya_refresh_token' => 'refresh',
            'tuya_endpoint' => 'apigw.tuyaus.com',
            'tuya_token_expires_at' => now()->addHour(),
        ]);

        Http::fake(['apigw.tuyaus.com/*' => Http::response(['success' => true, 'result' => null])]);

        (new TuyaCustomerApiClient)->get($integration, '/v1.0/m/life/users/homes');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/v1.0/m/token/'));
    }
}
```

- [ ] **Passo 2: Rodar**

```bash
./vendor/bin/sail test --filter=TuyaTokenRefreshTest
```

Esperado: PASS. Se falhar em `X-token: new-access`, o bug é o client estar assinando com o
model desatualizado — o `forceFill()->save()` precisa acontecer **antes** de montar os headers
do request real (a ordem no código da Task 4 já garante isso).

- [ ] **Passo 3: Commit**

```bash
git add tests/Feature/TuyaTokenRefreshTest.php
git commit -m "test(tuya): cover access token refresh before authenticated calls"
```

---

### Task 6: Login QR — `haauthorize`, `terminal_id` e `t`

Corrige **D3** e **D5**.

**Files:**
- Modify: `app/Services/Tuya/TuyaQrAuthService.php`
- Modify: `app/Services/Tuya/DTOs/TuyaTokenDTO.php`
- Modify: `database/migrations/2026_03_17_000001_add_tuya_fields_to_integrations_table.php`
- Modify: `app/Models/Integration.php`
- Modify: `app/Livewire/Integrations/TuyaConnect.php`
- Test: `tests/Unit/TuyaQrAuthServiceTest.php` (criar)

- [ ] **Passo 1: Escrever o teste**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tuya\TuyaQrAuthService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TuyaQrAuthServiceTest extends TestCase
{
    public function test_it_requests_the_qr_code_with_the_haauthorize_schema(): void
    {
        Http::fake(['apigw.iotbing.com/*' => Http::response([
            'success' => true,
            'result' => ['qrcode' => 'token-abc', 'expire_time' => 300],
        ])]);

        $dto = (new TuyaQrAuthService)->generateQrCode('user-code-1');

        $this->assertNotNull($dto);
        $this->assertSame('tuyaSmart--qrLogin?token=token-abc', $dto->qrUrl);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'schema=haauthorize')
            && str_contains($request->url(), 'clientid=HA_3y9q4ak7g4ephrvke')
            && str_contains($request->url(), 'usercode=user-code-1'));
    }

    public function test_it_captures_terminal_id_and_server_time_from_the_login_result(): void
    {
        Http::fake(['apigw.iotbing.com/*' => Http::response([
            'success' => true,
            't' => 1770000000000,
            'result' => [
                'access_token' => 'access',
                'refresh_token' => 'refresh',
                'uid' => 'uid-1',
                'expire_time' => 7200,
                'endpoint' => 'https://apigw.tuyaus.com',
                'terminal_id' => 'terminal-1',
            ],
        ])]);

        $token = (new TuyaQrAuthService)->pollLogin('token-abc', 'user-code-1');

        $this->assertNotNull($token);
        $this->assertSame('terminal-1', $token->terminalId);
        $this->assertSame(1770000000000, $token->serverTime);
        $this->assertSame('https://apigw.tuyaus.com', $token->endpoint);
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=TuyaQrAuthServiceTest
```

Esperado: FAIL — `schema=haauthorize` não encontrado / propriedade `terminalId` inexistente.

- [ ] **Passo 3: Atualizar o DTO**

Em `app/Services/Tuya/DTOs/TuyaTokenDTO.php`, o construtor passa a ser:

```php
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly int $expireTime,
        public readonly string $uid,
        public readonly ?string $endpoint = null,
        public readonly ?string $terminalId = null,
        /** Timestamp do servidor Tuya em milissegundos (campo `t` da resposta). */
        public readonly ?int $serverTime = null,
    ) {}
```

- [ ] **Passo 4: Corrigir o schema e capturar os campos novos**

Em `TuyaQrAuthService.php`:

```php
    private const SCHEMA = 'haauthorize';

    /** Prefixo do conteúdo do QR — diferente do parâmetro `schema` da requisição. */
    private const QR_SCHEMA = 'tuyaSmart';
```

Em `generateQrCode()`, a `$qrUrl` passa a usar `self::QR_SCHEMA`:

```php
            qrUrl: self::QR_SCHEMA.'--qrLogin?token='.$qrCode,
```

Em `pollLogin()`, ao montar o DTO:

```php
        return new TuyaTokenDTO(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expireTime: $expireTime,
            uid: $uid,
            endpoint: is_string($endpoint) ? $endpoint : null,
            terminalId: data_get($data, 'result.terminal_id'),
            serverTime: is_numeric($data['t'] ?? null) ? (int) $data['t'] : null,
        );
```

- [ ] **Passo 5: Persistir o `terminal_id`**

Em `database/migrations/2026_03_17_000001_add_tuya_fields_to_integrations_table.php`, dentro do
`up()`, depois da linha do `tuya_endpoint`:

```php
            $table->string('tuya_terminal_id')->nullable()->after('tuya_endpoint');
```

E no `down()`, adicionar `'tuya_terminal_id',` à lista do `dropColumn`.

Em `app/Models/Integration.php`, adicionar `'tuya_terminal_id',` ao `$fillable`.

Em `app/Livewire/Integrations/TuyaConnect.php`:

- `pollQr()` — incluir no `tokenJson`:

```php
                'terminal_id' => $token->terminalId,
                'server_time' => $token->serverTime,
```

- `saveIntegration()` — reconstruir o DTO com os campos novos e usar o `t` do servidor para a
  expiração (o relógio do servidor Tuya é a referência, não o nosso):

```php
        $token = new TuyaTokenDTO(
            accessToken: $tokenData['access_token'],
            refreshToken: $tokenData['refresh_token'],
            expireTime: (int) $tokenData['expire_time'],
            uid: $tokenData['uid'],
            endpoint: $tokenData['endpoint'] ?? null,
            terminalId: $tokenData['terminal_id'] ?? null,
            serverTime: isset($tokenData['server_time']) ? (int) $tokenData['server_time'] : null,
        );

        $expiresAt = $token->serverTime !== null
            ? now()->setTimestamp(intdiv($token->serverTime, 1000))->addSeconds($token->expireTime)
            : now()->addSeconds($token->expireTime);
```

e no array de update do `Integration::updateOrCreate`:

```php
                'tuya_token_expires_at' => $expiresAt,
                'tuya_terminal_id' => $token->terminalId,
```

- [ ] **Passo 6: Rodar migrations e testes**

```bash
./vendor/bin/sail artisan migrate:fresh
./vendor/bin/sail test --filter="TuyaQrAuthServiceTest|TuyaConnectImportTest"
```

Esperado: PASS nos dois.

- [ ] **Passo 7: Commit**

```bash
./vendor/bin/sail pint
git add -A
git commit -m "fix(tuya): use haauthorize schema and persist terminal id and server-based expiry"
```

---

### Task 7: `TuyaIntegrationService` sobre o client novo

**Files:**
- Modify: `app/Services/Tuya/TuyaIntegrationService.php`
- Modify: `app/Services/Tuya/TuyaQrAuthService.php` (remover a camada 2)

- [ ] **Passo 1: Injetar o client**

```php
    public function __construct(
        private readonly TuyaCustomerApiClient $client = new TuyaCustomerApiClient,
    ) {}
```

- [ ] **Passo 2: Trocar `customerRequest()` privado pelo client**

Remover o método privado `customerRequest()` de `TuyaIntegrationService` e trocar as chamadas:

```php
    public function listDevices(Integration $integration): Collection
    {
        $homes = $this->client->get($integration, '/v1.0/m/life/users/homes');

        if (! is_array($homes)) {
            return collect();
        }

        $devices = collect();

        foreach ($homes as $home) {
            $homeId = $home['ownerId'] ?? null;

            if ($homeId === null || $homeId === '') {
                continue;
            }

            $homeDevices = $this->client->get(
                $integration,
                '/v1.0/m/life/ha/home/devices',
                ['homeId' => (string) $homeId],
            );

            if (is_array($homeDevices)) {
                $devices = $devices->merge($homeDevices);
            }
        }

        return $devices
            ->map(fn (array $device): TuyaDeviceDTO => new TuyaDeviceDTO(
                id: (string) ($device['id'] ?? ''),
                name: (string) ($device['name'] ?? 'Dispositivo sem nome'),
                category: (string) ($device['category'] ?? ''),
                online: (bool) ($device['online'] ?? false),
                productId: $device['product_id'] ?? $device['productId'] ?? null,
                productName: $device['product_name'] ?? $device['productName'] ?? null,
                icon: $device['icon'] ?? null,
                status: is_array($device['status'] ?? null) ? $device['status'] : [],
            ))
            ->filter(fn (TuyaDeviceDTO $device): bool => $device->id !== '')
            ->values();
    }
```

> Os fallbacks `$home['homeId'] ?? $home['home_id'] ?? $home['id']` e
> `$response['list'] ?? $response['homes']` foram chutes de quem não tinha o SDK à mão.
> `home.py:17` confirma que o campo é `ownerId` e que `result` já é a lista. Simplifique.

- [ ] **Passo 3: Esvaziar a camada 2 do `TuyaQrAuthService`**

Remover de `TuyaQrAuthService.php`: `customerRequest()`, `getDevices()`, `aesGcmEncrypt()`,
`aesGcmDecrypt()`, `randomNonce()`, `normalizeEndpoint()` e as constantes `NONCE_ALPHABET`.
O serviço fica só com `generateQrCode()` e `pollLogin()`.

`TuyaConnect::pollQr()` chamava `$service->getDevices($token)` — como nesse ponto a
`Integration` ainda não existe, crie um model **não persistido** para alimentar o client:

```php
            $probe = new Integration([
                'tuya_access_token' => $token->accessToken,
                'tuya_refresh_token' => $token->refreshToken,
                'tuya_endpoint' => $token->endpoint,
            ]);
            $probe->tuya_token_expires_at = now()->addSeconds($token->expireTime);

            $deviceDtos = app(TuyaIntegrationService::class)->listDevices($probe)->all();
```

> Como `tuya_token_expires_at` está no futuro, o refresh não dispara — e o model nunca é salvo.

- [ ] **Passo 4: Rodar a suíte inteira**

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test
```

Esperado: todos passando.

- [ ] **Passo 5: Commit**

```bash
git add -A
git commit -m "refactor(tuya): route device queries through TuyaCustomerApiClient"
```

---

# Fase 2 — Capacidades reais do dispositivo

### Task 8: Ler `/specifications` e decidir o DP por capability

Corrige **D6** e **D7**. Sem isso o sistema manda `temporary_password_creat` (DP de fechadura
**Zigbee residencial**, 21 bytes) para uma fechadura Wi-Fi `jtmspro`, que usa outro conjunto
de DPs.

**Files:**
- Modify: `database/migrations/2026_03_17_000003_add_tuya_context_to_devices_table.php`
- Modify: `app/Models/Device.php`
- Modify: `app/Services/Tuya/TuyaIntegrationService.php`
- Test: `tests/Unit/DeviceTuyaCapabilitiesTest.php` (criar)

- [ ] **Passo 1: Escrever o teste**

```php
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
```

- [ ] **Passo 2: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=DeviceTuyaCapabilitiesTest
```

Esperado: FAIL — `tuya_functions` não é fillable / `supportsTuyaTemporaryPassword` não existe.

- [ ] **Passo 3: Coluna nova na migration existente**

Em `database/migrations/2026_03_17_000003_add_tuya_context_to_devices_table.php`, no `up()`,
depois de `tuya_status_payload`:

```php
            $table->json('tuya_functions')->nullable()->after('tuya_status_payload');
```

E no `down()`, adicionar `'tuya_functions',` à lista do `dropColumn`.

- [ ] **Passo 4: Model**

Em `app/Models/Device.php`:

```php
    // $fillable — adicionar
        'tuya_functions',

    // $casts — adicionar
        'tuya_functions' => 'array',
```

E substituir `isTuyaLock()` / `supportsPlaceAccessCodes()`:

```php
    private const TUYA_LOCK_CATEGORIES = ['ms', 'jtmspro', 'jtmsbh', 'mk'];

    private const TUYA_TEMPORARY_PASSWORD_DP = 'temporary_password_creat';

    /** Fechadura Tuya: exige categoria conhecida — categoria nula significa "ainda não sincronizado". */
    public function isTuyaLock(): bool
    {
        return $this->brand === DeviceBrandEnum::Tuya
            && in_array($this->tuya_category, self::TUYA_LOCK_CATEGORIES, true);
    }

    /**
     * Só manda PIN para fechadura que declarou o DP na resposta de /specifications.
     * Fechadura Wi-Fi (jtmspro) costuma expor unlock_method_create em vez deste DP.
     */
    public function supportsTuyaTemporaryPassword(): bool
    {
        return $this->isTuyaLock()
            && in_array(self::TUYA_TEMPORARY_PASSWORD_DP, $this->tuya_functions ?? [], true);
    }

    public function supportsPlaceAccessCodes(): bool
    {
        return $this->brand === DeviceBrandEnum::Portatec
            || $this->supportsTuyaTemporaryPassword();
    }
```

- [ ] **Passo 5: Buscar as specifications**

Em `TuyaIntegrationService.php`:

```php
    /**
     * Busca os function codes suportados pelo dispositivo.
     * Port de device.py DeviceRepository.update_device_specification.
     *
     * @return list<string>
     */
    public function syncDeviceSpecifications(Device $device): array
    {
        $integration = $this->resolveIntegration($device);

        $result = $this->client->get(
            $integration,
            "/v1.1/m/life/{$device->external_device_id}/specifications",
        );

        if (! is_array($result) || ! is_array($result['functions'] ?? null)) {
            return [];
        }

        $codes = collect($result['functions'])
            ->pluck('code')
            ->filter(fn ($code): bool => is_string($code) && $code !== '')
            ->values()
            ->all();

        $device->forceFill(['tuya_functions' => $codes])->save();

        return $codes;
    }
```

E chamar dentro de `refreshDeviceSnapshot()`, logo antes do `return $snapshot;`:

```php
        $this->syncDeviceSpecifications($device);
```

- [ ] **Passo 6: Chamar no import**

Em `TuyaConnect::saveIntegration()`, guardar o device criado e sincronizar as specs:

```php
            $device = Device::updateOrCreate(/* ...como está hoje... */);
            $device->deviceUsers()->firstOrCreate(['user_id' => Auth::id()]);

            try {
                app(TuyaIntegrationService::class)->syncDeviceSpecifications($device);
            } catch (\Throwable $exception) {
                report($exception);
            }
```

> O `try/catch` é deliberado: uma falha de spec não pode derrubar a importação inteira.
> O device fica sem `tuya_functions` e, por consequência, sem receber PIN — que é o
> comportamento seguro.

- [ ] **Passo 7: Rodar migrations e testes**

```bash
./vendor/bin/sail artisan migrate:fresh
./vendor/bin/sail test
```

Esperado: todos passando. **`AccessCodeSyncServiceTest` provavelmente vai quebrar** — os
devices Tuya das fixtures não têm `tuya_functions`. Ajuste as fixtures para incluir
`'tuya_functions' => ['temporary_password_creat', 'temporary_password_delete']` e
`'tuya_category' => 'ms'`; é exatamente a regressão que a Task 8 quer prevenir.

- [ ] **Passo 8: Commit**

```bash
./vendor/bin/sail pint
git add -A
git commit -m "feat(tuya): read device specifications and gate PIN sync on declared DP support"
```

---

# Fase 3 — PIN na fechadura

### Task 9: Payload do DP em teste isolado

O layout de 21 bytes está **confirmado** contra a documentação oficial da Tuya
(Residential Lock DP Reference, DP 24 = create / DP 25 = delete). Merece teste próprio porque é
binário e não dá para inspecionar rodando.

**Files:**
- Test: `tests/Unit/TuyaTemporaryPasswordPayloadTest.php`
- Modify: `app/Services/Tuya/TuyaIntegrationService.php`

- [ ] **Passo 1: Escrever o teste**

```php
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
```

- [ ] **Passo 2: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=TuyaTemporaryPasswordPayloadTest
```

Esperado: FAIL — `buildCreatePayload` não existe.

- [ ] **Passo 3: Extrair os builders e reescrever os dois métodos públicos**

Em `TuyaIntegrationService.php` — troque o `use RuntimeException;` do topo por
`use App\Exceptions\TuyaApiException;` e substitua `createTemporaryPasswordViaDP()` e
`deleteTemporaryPassword()` por (o `$this->client` vem do construtor da Task 7):

```php
    private const DP_CREATE = 'temporary_password_creat';

    private const DP_DELETE = 'temporary_password_delete';

    /**
     * DP 24 — cria senha temporária. Payload raw de 21 bytes em Base64.
     * https://developer.tuya.com/en/docs/iot/zigbee-doorlock-dp?id=K9fembhbeab0p
     *
     * Retorna "tuyaSeq:serverSeq", que identifica a senha para remoção posterior.
     *
     * @throws TuyaApiException
     */
    public function createTemporaryPassword(
        Device $device,
        string $pin,
        int $effectiveTime,
        int $invalidTime,
    ): string {
        if (strlen($pin) !== 6 || ! ctype_digit($pin)) {
            throw new TuyaApiException('PIN deve ter exatamente 6 dígitos.');
        }

        if (! $device->supportsTuyaTemporaryPassword()) {
            throw new TuyaApiException(
                "Dispositivo {$device->id} não declara o DP ".self::DP_CREATE.'.'
            );
        }

        $integration = $this->resolveIntegration($device);
        $tuyaSeq = random_int(0, 65535);
        $serverSeq = random_int(0, 65535);

        $this->client->post(
            $integration,
            "/v1.1/m/thing/{$device->external_device_id}/commands",
            body: ['commands' => [[
                'code' => self::DP_CREATE,
                'value' => $this->buildCreatePayload($tuyaSeq, $serverSeq, $pin, $effectiveTime, $invalidTime),
            ]]],
        );

        return "{$tuyaSeq}:{$serverSeq}";
    }

    /**
     * DP 25 — remove senha temporária. Payload raw de 6 bytes em Base64.
     *
     * @throws TuyaApiException
     */
    public function deleteTemporaryPassword(Device $device, string $externalReference): void
    {
        [$tuyaSeq, $serverSeq] = $this->parseReference($externalReference);

        $this->client->post(
            $this->resolveIntegration($device),
            "/v1.1/m/thing/{$device->external_device_id}/commands",
            body: ['commands' => [[
                'code' => self::DP_DELETE,
                'value' => $this->buildDeletePayload($tuyaSeq, $serverSeq),
            ]]],
        );
    }

    private function buildCreatePayload(
        int $tuyaSeq,
        int $serverSeq,
        string $pin,
        int $effectiveTime,
        int $invalidTime,
    ): string {
        return base64_encode(
            pack('n', $tuyaSeq)          // [0..1]  Tuya serial number
            .pack('n', $serverSeq)       // [2..3]  server serial number
            .pack('n', 0x0000)           // [4..5]  lock manufacturer id
            .pack('N', $effectiveTime)   // [6..9]  início, unix big-endian
            .pack('N', $invalidTime)     // [10..13] fim, unix big-endian
            .chr(0x00)                   // [14]    não é one-time
            .$pin                        // [15..20] 6 dígitos ASCII
        );
    }

    private function buildDeletePayload(int $tuyaSeq, int $serverSeq): string
    {
        return base64_encode(
            pack('n', $tuyaSeq)
            .pack('n', $serverSeq)
            .pack('n', 0x0000)
        );
    }

    /** @return array{0: int, 1: int} */
    private function parseReference(string $externalReference): array
    {
        $parts = explode(':', $externalReference, 2);

        if (count($parts) !== 2 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1])) {
            throw new TuyaApiException("Referência de senha temporária inválida: {$externalReference}");
        }

        return [(int) $parts[0], (int) $parts[1]];
    }
```

- [ ] **Passo 4: Rodar e ver passar**

```bash
./vendor/bin/sail test --filter=TuyaTemporaryPasswordPayloadTest
```

Esperado: PASS.

- [ ] **Passo 5: Commit**

```bash
./vendor/bin/sail pint
git add -A
git commit -m "feat(tuya): rewrite temporary password DP commands on the new client"
```

---

### Task 10: Adequar o `AccessCodeSyncService`

Os métodos mudaram de nome e de contrato (agora lançam exceção em vez de devolver `[]`/`bool`).

**Files:**
- Modify: `app/Services/AccessCodeSyncService.php`
- Modify: `tests/Unit/AccessCodeSyncServiceTest.php`

- [ ] **Passo 1: Trocar as chamadas**

Em `syncTuyaAccessCodeToDevice()`:

```php
            $externalReference = $this->tuyaIntegrationService->createTemporaryPassword(
                device: $device,
                pin: $accessCode->pin,
                effectiveTime: $accessCode->start->timestamp,
                invalidTime: $invalidTime,
            );
```

Em `deleteTuyaAccessCodeFromDevice()`, o `deleteTemporaryPassword()` agora é `void` e lança em
falha — o `try/catch` que já existe cobre isso; só remova qualquer uso do retorno booleano.

- [ ] **Passo 2: Trocar os filtros de capability**

Onde o serviço usa `$device->isTuyaLock()` para decidir **enviar PIN**, trocar por
`$device->supportsTuyaTemporaryPassword()`. São três pontos:
`syncAccessCodesToDevice()`, `syncSingleAccessCode()` e `syncDeletedAccessCode()`.

- [ ] **Passo 3: Atualizar as fixtures do teste**

Em `tests/Unit/AccessCodeSyncServiceTest.php`, todo device Tuya criado precisa de:

```php
            'tuya_category' => 'ms',
            'tuya_functions' => ['temporary_password_creat', 'temporary_password_delete'],
```

E adicione um caso de regressão novo:

```php
    public function test_it_does_not_send_pin_to_a_tuya_device_without_the_temporary_password_dp(): void
    {
        $placeId = DB::table('places')->insertGetId([
            'name' => 'Sem DP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $device = Device::create([
            'name' => 'Fechadura Wi-Fi',
            'brand' => 'tuya',
            'external_device_id' => 'dev-jtmspro',
            'tuya_category' => 'jtmspro',
            'tuya_functions' => ['unlock_method_create'],
        ]);
        $device->places()->attach($placeId);

        $tuyaMock = Mockery::mock(TuyaIntegrationService::class);
        $tuyaMock->shouldNotReceive('createTemporaryPassword');

        $this->app->instance(DeviceCommandService::class, Mockery::mock(DeviceCommandService::class));
        $this->app->instance(TuyaIntegrationService::class, $tuyaMock);

        app(AccessCodeSyncService::class)->syncAccessCodesToDevice($device);
    }
```

Segue o estilo do arquivo: `Mockery` + `$this->app->instance()` + `DB::table()->insertGetId()`
para o place. Nenhuma factory nova.

- [ ] **Passo 4: Rodar a suíte inteira**

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test
```

Esperado: tudo verde.

- [ ] **Passo 5: Commit**

```bash
git add -A
git commit -m "refactor(tuya): gate access code sync on declared lock capabilities"
```

---

# Fase 4 — Eventos via MQTT da Tuya

Resolve **D8** e responde a pendência documental do plano original
(`docs/plano-refatoracao-integracao-tuya.md`: *"Confirmar oficialmente se o fluxo de QR code
permite webhook"*).

**A resposta é: não é webhook nem polling — é MQTT.** O SDK obtém as credenciais em
`POST /v1.0/m/life/ha/access/config` com body `{"linkId": "<id>"}` e recebe
`{url, clientId, username, password, expireTime, topic: {ownerId: {sub}, devId: {sub}}}`.
As mensagens chegam em **JSON puro, sem criptografia** (`mq.py:_on_message` só faz
`json.loads`), e a conexão precisa ser refeita a cada `expireTime` (~2h).

Formato da mensagem (`manager.py:on_message`):
- `protocol == 4` → `data.devId` + `data.status` (lista de `{code, value}`) — **é aqui que
  chega o report do DP de senha temporária**;
- `protocol == 20` → `data.bizCode` ∈ `online`, `offline`, `nameUpdate`, `dpNameUpdate`,
  `bindUser`, `delete`, com `data.bizData.devId`.

> Esta fase é independente das anteriores e pode ser entregue depois. As Fases 0–3 já
> produzem software funcionando.

### Task 11: `TuyaMqttService`

**Files:**
- Create: `app/Services/Tuya/TuyaMqttService.php`
- Test: `tests/Unit/TuyaMqttServiceTest.php`

- [ ] **Passo 1: Escrever o teste**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use App\Services\Tuya\TuyaMqttService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TuyaMqttServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_the_reported_status_of_a_device(): void
    {
        $device = Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'dev-1',
            'tuya_status_payload' => [],
        ]);

        (new TuyaMqttService)->handleMessage([
            'protocol' => 4,
            'data' => [
                'devId' => 'dev-1',
                'status' => [['code' => 'lock_motor_state', 'value' => true]],
            ],
        ]);

        $device->refresh();
        $this->assertSame(
            [['code' => 'lock_motor_state', 'value' => true]],
            $device->tuya_status_payload,
        );
    }

    public function test_it_updates_online_state_from_biz_code(): void
    {
        $device = Device::create([
            'name' => 'Fechadura',
            'brand' => DeviceBrandEnum::Tuya,
            'external_device_id' => 'dev-2',
            'tuya_online' => true,
        ]);

        (new TuyaMqttService)->handleMessage([
            'protocol' => 20,
            'data' => ['bizCode' => 'offline', 'bizData' => ['devId' => 'dev-2']],
        ]);

        $this->assertFalse($device->refresh()->tuya_online);
    }

    public function test_it_ignores_messages_for_unknown_devices(): void
    {
        (new TuyaMqttService)->handleMessage([
            'protocol' => 4,
            'data' => ['devId' => 'nao-existe', 'status' => []],
        ]);

        $this->assertDatabaseCount('devices', 0);
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=TuyaMqttServiceTest
```

Esperado: FAIL — classe não encontrada.

- [ ] **Passo 3: Implementar**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tuya;

use App\Models\Device;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Canal de push da Tuya (MQTT). Port de tuya_sharing/mq.py e manager.py:on_message.
 * As mensagens chegam em JSON puro — não há criptografia nesta camada.
 */
class TuyaMqttService
{
    private const PROTOCOL_DEVICE_REPORT = 4;

    private const PROTOCOL_OTHER = 20;

    private const ONLINE_BIZ_CODES = ['online' => true, 'offline' => false];

    public function __construct(
        private readonly TuyaCustomerApiClient $client = new TuyaCustomerApiClient,
    ) {}

    /**
     * Credenciais e tópicos do broker MQTT da Tuya. Válidas por `expireTime` segundos.
     *
     * @return array<string, mixed>
     */
    public function config(Integration $integration): array
    {
        $result = $this->client->post(
            $integration,
            '/v1.0/m/life/ha/access/config',
            body: ['linkId' => 'portatec.'.Str::uuid()],
        );

        if (! is_array($result)) {
            return [];
        }

        return $result;
    }

    /** @param array<string, mixed> $message */
    public function handleMessage(array $message): void
    {
        $protocol = (int) ($message['protocol'] ?? 0);
        $data = $message['data'] ?? [];

        if (! is_array($data)) {
            return;
        }

        if ($protocol === self::PROTOCOL_DEVICE_REPORT) {
            $this->handleDeviceReport($data);

            return;
        }

        if ($protocol === self::PROTOCOL_OTHER) {
            $this->handleBizEvent($data);
        }
    }

    /** @param array<string, mixed> $data */
    private function handleDeviceReport(array $data): void
    {
        $device = $this->findDevice($data['devId'] ?? null);

        if ($device === null) {
            return;
        }

        $status = is_array($data['status'] ?? null) ? $data['status'] : [];

        $device->forceFill([
            'tuya_status_payload' => $status,
            'last_sync' => now(),
        ])->save();

        Log::info('[Tuya MQTT] status reportado', [
            'device_id' => $device->id,
            'codes' => collect($status)->pluck('code')->all(),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function handleBizEvent(array $data): void
    {
        $bizCode = (string) ($data['bizCode'] ?? '');

        if (! array_key_exists($bizCode, self::ONLINE_BIZ_CODES)) {
            return;
        }

        $device = $this->findDevice(data_get($data, 'bizData.devId'));

        if ($device === null) {
            return;
        }

        $device->forceFill([
            'tuya_online' => self::ONLINE_BIZ_CODES[$bizCode],
            'last_sync' => now(),
        ])->save();
    }

    private function findDevice(mixed $externalId): ?Device
    {
        if (! is_string($externalId) || $externalId === '') {
            return null;
        }

        return Device::query()->where('external_device_id', $externalId)->first();
    }
}
```

- [ ] **Passo 4: Rodar e ver passar**

```bash
./vendor/bin/sail test --filter=TuyaMqttServiceTest
```

Esperado: PASS.

- [ ] **Passo 5: Commit**

```bash
./vendor/bin/sail pint
git add -A
git commit -m "feat(tuya): add MQTT push service for device status and online events"
```

---

### Task 12: Comando `tuya:subscribe`

**Files:**
- Create: `app/Console/Commands/TuyaSubscribeCommand.php`
- Modify: `docker/prod/` (supervisord — ver passo 4)

O broker é dinâmico (host, porta e credenciais vêm da API), então **não** dá para usar a
facade `MQTT` como o `mqtt:subscribe` existente faz. Instancie o `MqttClient` direto.

- [ ] **Passo 1: Implementar o comando**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Integration;
use App\Services\Tuya\TuyaMqttService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class TuyaSubscribeCommand extends Command
{
    protected $signature = 'tuya:subscribe {integration? : ID da integração Tuya}
                                           {--once : Processa uma iteração e sai}';

    protected $description = 'Assina o broker MQTT da Tuya e aplica os eventos recebidos.';

    public function handle(TuyaMqttService $service): int
    {
        $integration = $this->resolveIntegration();

        if ($integration === null) {
            $this->error('Nenhuma integração Tuya encontrada.');

            return self::FAILURE;
        }

        $config = $service->config($integration);
        $url = (string) ($config['url'] ?? '');

        if ($url === '') {
            $this->error('A Tuya não devolveu URL de MQTT.');

            return self::FAILURE;
        }

        $parts = parse_url($url);
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

        $topic = str_replace(
            '{ownerId}',
            (string) $integration->tuya_uid,
            (string) data_get($config, 'topic.ownerId.sub'),
        );

        $client->subscribe($topic, function (string $topic, string $message) use ($service): void {
            $payload = json_decode($message, true);

            if (! is_array($payload)) {
                Log::warning('[Tuya MQTT] payload inválido', ['topic' => $topic]);

                return;
            }

            $service->handleMessage($payload);
        });

        Log::info('[Tuya MQTT] subscriber iniciado', [
            'integration_id' => $integration->id,
            'topic' => $topic,
            'expire_time' => $config['expireTime'] ?? null,
        ]);

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, fn () => $client->interrupt());
        pcntl_signal(SIGTERM, fn () => $client->interrupt());

        $client->loop(! $this->option('once'));
        $client->disconnect();

        return self::SUCCESS;
    }

    private function resolveIntegration(): ?Integration
    {
        $query = Integration::query()
            ->whereHas('platform', fn ($q) => $q->where('slug', 'tuya'))
            ->whereNotNull('tuya_refresh_token');

        $id = $this->argument('integration');

        return $id === null
            ? $query->latest('updated_at')->first()
            : $query->whereKey($id)->first();
    }
}
```

- [ ] **Passo 2: Testar contra a conta real**

```bash
./vendor/bin/sail artisan tuya:subscribe --once
```

Esperado: `[Tuya MQTT] subscriber iniciado` no log. Acione a fechadura fisicamente e confira
que o `tuya_status_payload` do device muda.

> Este é o único passo desta SPEC que **exige o hardware**. Se falhar aqui, o log de
> `TuyaApiException` no `/v1.0/m/life/ha/access/config` diz se o problema é permissão da
> conta ou protocolo.

- [ ] **Passo 3: Confirmar o report do DP de senha**

Com o subscriber rodando, crie um PIN pela UI e verifique se chega um status com o código
`temporary_password_creat`. O byte [6] do payload de 7 bytes é o resultado
(`0`=falha, `1`=sucesso, `2`=duplicado, `3`=limite). **É aqui que se valida se os
`tuyaSeq`/`serverSeq` gerados pelo cliente na Task 9 são aceitos** — se o report devolver
seriais diferentes dos enviados, `AccessCodeDeviceSync.external_reference` precisa passar a ser
preenchido pelo report, não pela criação.

- [ ] **Passo 4: Supervisord em produção**

Em `docker/prod/supervisord.conf`, logo depois do bloco `[program:mqtt-subscriber]`
(linha 60), acrescentar:

```ini
[program:tuya-subscriber]
command=php /var/www/artisan tuya:subscribe
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
user=www-data
```

O `autorestart=true` é o mecanismo de renovação: as credenciais MQTT expiram em ~2h, o
`loop()` retorna, o processo sai e o supervisor sobe de novo pedindo credenciais novas.

- [ ] **Passo 5: Commit**

```bash
./vendor/bin/sail pint
git add -A
git commit -m "feat(tuya): add tuya:subscribe command for the Tuya MQTT push channel"
```

---

# Fase 5 — Acabamento

### Task 13: Filtro "Sem local" no `place-select`

Corrige **D10**: `resources/views/livewire/devices/index.blade.php` passa
`:include-unassigned="true"` e `unassigned-option-label="Sem local"`, mas o componente não tem
essas props — os atributos vazam como HTML no `<select>` e a opção não aparece.
`App\Livewire\Devices\Index` já trata `placeId === 'unassigned'`.

**Files:**
- Modify: `app/View/Components/PlaceSelect.php`
- Modify: `resources/views/components/place-select.blade.php`
- Test: `tests/Feature/DevicesIndexFilterTest.php` (criar)

- [ ] **Passo 1: Escrever o teste**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeviceBrandEnum;
use App\Enums\PlaceRoleEnum;
use App\Livewire\Devices\Index;
use App\Models\Device;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DevicesIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_the_unassigned_option_and_filters_by_it(): void
    {
        $user = User::factory()->create();
        $place = Place::create(['name' => 'Casa 1']);
        $user->placeUsers()->create(['place_id' => $place->id, 'role' => PlaceRoleEnum::Admin]);

        $attached = Device::create([
            'name' => 'Com local',
            'brand' => DeviceBrandEnum::Portatec,
            'external_device_id' => 'chip-1',
        ]);
        $attached->places()->attach($place->id);

        Device::create([
            'name' => 'Sem local nenhum',
            'brand' => DeviceBrandEnum::Portatec,
            'external_device_id' => 'chip-2',
            'place_id' => null,
        ]);

        $this->actingAs($user);

        Livewire::test(Index::class)
            ->assertSee('Sem local')
            ->set('placeId', 'unassigned')
            ->assertSee('Sem local nenhum')
            ->assertDontSee('Com local');
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

```bash
./vendor/bin/sail test --filter=DevicesIndexFilterTest
```

Esperado: FAIL em `assertSee('Sem local')`.

- [ ] **Passo 3: Adicionar as props**

Em `app/View/Components/PlaceSelect.php`, no construtor, depois de `$emptyOptionLabel`:

```php
        public bool $includeUnassigned = false,
        public string $unassignedOptionLabel = 'Sem local',
```

Em `resources/views/components/place-select.blade.php`, logo depois do bloco `@if ($includeEmpty)`:

```blade
        @if ($includeUnassigned)
            <option value="unassigned">{{ $unassignedOptionLabel }}</option>
        @endif
```

- [ ] **Passo 4: Rodar e ver passar**

```bash
./vendor/bin/sail test --filter=DevicesIndexFilterTest
```

Esperado: PASS.

- [ ] **Passo 5: Commit**

```bash
./vendor/bin/sail pint
git add -A
git commit -m "fix(devices): render the unassigned option in the place filter"
```

---

### Task 14: i18n das telas Tuya

Regra 5 do `AGENTS.md`: nenhuma string de UI fora de `resources/lang/pt_BR/app.php`.
As telas novas da PR nasceram com texto literal.

**Files:**
- Modify: `resources/lang/pt_BR/app.php`
- Modify: `resources/views/livewire/integrations/tuya-connect.blade.php`
- Modify: `resources/views/livewire/devices/integrations/index.blade.php`
- Modify: `app/Livewire/Integrations/TuyaConnect.php`

- [ ] **Passo 1: Listar as strings literais**

```bash
./vendor/bin/sail exec -T laravel.test grep -n "[A-Za-zÀ-ú]\{4,\}" \
  resources/views/livewire/integrations/tuya-connect.blade.php \
  resources/views/livewire/devices/integrations/index.blade.php | grep -v "__(\|class=\|href=\|wire:"
```

- [ ] **Passo 2: Adicionar as chaves**

Em `resources/lang/pt_BR/app.php`, num bloco novo no fim do array:

```php
    // Integração Tuya
    'tuya_connect_title' => 'Conectar conta Tuya / SmartLife',
    'tuya_user_code_label' => 'Código do usuário',
    'tuya_user_code_help' => 'No app SmartLife: Eu → Configurações → Conta e segurança → Código do usuário.',
    'tuya_generate_qr' => 'Gerar QR code',
    'tuya_scan_instruction' => 'Escaneie o QR code no app SmartLife e confirme a autorização.',
    'tuya_qr_expired' => 'O QR code expirou. Gere um novo.',
    'tuya_select_devices' => 'Selecione os dispositivos que deseja importar',
    'tuya_save_integration' => 'Salvar integração',
    'tuya_integrations_title' => 'Integrações de dispositivos',
    'tuya_no_integrations' => 'Nenhuma conta Tuya conectada.',
    'tuya_connect_action' => 'Conectar Tuya',
    'tuya_last_sync' => 'Última sincronização',
    'tuya_invalid_user_code' => 'Não foi possível gerar o QR code. Verifique o código do usuário.',
    'tuya_session_expired' => 'Sessão inválida. Por favor, recomece.',
    'tuya_connected' => 'Integração Tuya conectada com sucesso. Vincule os dispositivos importados a um local para sincronizar PINs.',
```

> Confira cada chave contra o texto real das views antes de aplicar — a lista acima cobre o
> que existe hoje, mas as views podem ter mudado.

- [ ] **Passo 3: Substituir nas views e no componente**

Trocar cada literal por `__('app.<chave>')`, incluindo as mensagens de
`$this->errorMessage` e `session()->flash('status', ...)` em `TuyaConnect.php`.

- [ ] **Passo 4: Rodar a suíte**

```bash
./vendor/bin/sail test
```

Esperado: tudo verde (`TuyaConnectImportTest` não assere texto).

- [ ] **Passo 5: Commit**

```bash
./vendor/bin/sail pint
git add -A
git commit -m "i18n(tuya): move Tuya screen strings to pt_BR translations"
```

---

### Task 15: Atualizar a documentação

O `AGENTS.md` §11 (mergeado no passo anterior) descreve o estado **antigo** e agora tem
informação errada: `SCHEMA = 'tuyaSmart'` e o mapeamento SDK→PHP apontando tudo para
`TuyaQrAuthService`.

**Files:**
- Modify: `AGENTS.md`
- Delete: `docs/plano-refatoracao-integracao-tuya.md`

- [ ] **Passo 1: Corrigir §11**

- Constantes: `SCHEMA = 'haauthorize'` (parâmetro da requisição) e `QR_SCHEMA = 'tuyaSmart'`
  (prefixo do conteúdo do QR) — deixar explícito que são coisas diferentes.
- Remover o parágrafo sobre `Client.php` / `TuyaService.php` serem "legado não usado";
  eles não existem mais.
- Atualizar a tabela de mapeamento: camada 2 agora é `TuyaCustomerApiClient`, não
  `TuyaQrAuthService`.
- Documentar o refresh: `GET /v1.0/m/token/{refresh_token}`, rotaciona os dois tokens, e o
  motivo (o `hash_key` deriva do `refresh_token`).
- Documentar as colunas novas: `integrations.tuya_terminal_id`, `devices.tuya_functions`.
- Acrescentar uma subseção sobre o canal MQTT: endpoint de config, formato dos tópicos,
  `protocol` 4 vs 20, JSON puro, reconexão a cada `expireTime`.

- [ ] **Passo 2: Remover o plano antigo e esta SPEC**

```bash
rm docs/plano-refatoracao-integracao-tuya.md docs/spec-conclusao-integracao-tuya.md
```

O checklist do plano foi absorvido por esta SPEC, e a pendência documental
("webhook ou polling?") foi respondida — é MQTT. Esta SPEC, por sua vez, é documento de
trabalho: o que precisa sobreviver ao merge já está no `AGENTS.md` §11 (Passo 1).

- [ ] **Passo 3: Commit**

```bash
git add -A
git commit -m "docs(tuya): update AGENTS.md reference and drop working documents"
```

---

## Ordem de entrega recomendada

| Fase | Tasks | Entrega |
|---|---|---|
| 0 | 1–2 | limpeza; nada muda funcionalmente |
| 1 | 3–7 | **destrava o `sign invalid`** — testar com a fechadura real aqui |
| 2 | 8 | para de mandar DP às cegas |
| 3 | 9–10 | PIN com sucesso/erro corretos |
| 4 | 11–12 | eventos e confirmação do DP |
| 5 | 13–15 | acabamento e docs |

Depois da Fase 1, pare e teste contra o hardware antes de investir nas seguintes. Se o
`sign invalid` persistir mesmo com o refresh funcionando, o próximo suspeito é o `X-sid`:
o Python manda o header com valor vazio e o exclui da string de assinatura — confirme com
`Http::assertSent()` que o Guzzle não está suprimindo o header.

## Riscos conhecidos

1. **Serial numbers da senha temporária.** A Task 9 gera `tuyaSeq`/`serverSeq` no cliente. A
   documentação da Tuya trata esses campos como atribuídos pelo servidor. Só o report do DP
   (Task 12, passo 3) resolve isso. Se divergirem, o `external_reference` passa a vir do
   report — o que torna a **Fase 4 pré-requisito da remoção de PIN**, não opcional.
2. **Fechadura Wi-Fi.** Se o device real for `jtmspro` e `/specifications` não listar
   `temporary_password_creat`, a Fase 3 não o atende. Nesse caso a saída é o DP 49
   (`remote_no_dp_key`, chave de desbloqueio remoto de 8 bytes ASCII com janela de validade),
   que é um payload diferente e precisa de uma spec própria. Rodar a Task 8 contra o hardware
   **antes** da Fase 3 elimina essa incerteza.
3. **`AccessCodeSyncService` roda síncrono.** Cada PIN vira uma chamada HTTP à Tuya dentro do
   `AccessCodeObserver`. Com várias fechaduras por place, a criação de reserva fica lenta.
   Fora do escopo desta SPEC, mas vale enfileirar depois que o fluxo estabilizar.
