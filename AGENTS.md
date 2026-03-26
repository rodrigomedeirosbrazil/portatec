# AGENTS.md

## Execucao de PHP/Composer

- Sempre execute comandos de PHP e Composer via Sail neste repositório.
- Use `./vendor/bin/sail` como prefixo para comandos de desenvolvimento e manutenção.

## Exemplos obrigatórios

- Composer: `./vendor/bin/sail composer <comando>`
- Artisan: `./vendor/bin/sail artisan <comando>`
- PHPUnit/Pest: `./vendor/bin/sail test` ou `./vendor/bin/sail php artisan test`
- Pint: `./vendor/bin/sail pint`

## Proibido (neste repo)

- Não executar `composer ...`, `php artisan ...`, `phpunit ...` e `pint ...` diretamente no host.

---

## Integração Tuya — Referência técnica

### Visão geral

O Portatec integra dispositivos Tuya **sem conta de developer** e **sem credenciais do portal `iot.tuya.com`**. Toda a autenticação e comunicação usa o mesmo mecanismo do Home Assistant — o `tuya-device-sharing-sdk` — via `apigw.iotbing.com`.

O arquivo `app/Services/Tuya/Client.php` e `TuyaService.php` existem no repositório mas **não são usados na integração atual**. São legado de uma abordagem anterior que exigia conta de developer. Não referenciar nem instanciar essas classes.

---

### `TuyaQrAuthService.php` — serviço principal

**Toda a comunicação Tuya passa por este serviço.** Ele implementa três camadas:

#### Camada 1 — QR login (sem autenticação)

Baseado em `tuya_sharing/user.py` — classe `LoginControl` do SDK Python.

```
POST https://apigw.iotbing.com/v1.0/m/life/home-assistant/qrcode/tokens
     ?clientid=HA_3y9q4ak7g4ephrvke&usercode={user_code}&schema=tuyaSmart
# Sem headers, sem body, sem assinatura.

GET  https://apigw.iotbing.com/v1.0/m/life/home-assistant/qrcode/tokens/{token}
     ?clientid=HA_3y9q4ak7g4ephrvke&usercode={user_code}
# Polling até success=true. Retorna access_token, refresh_token, uid.
```

Constantes fixas — **não alterar**:
```php
CLIENT_ID = 'HA_3y9q4ak7g4ephrvke'
SCHEMA    = 'tuyaSmart'
BASE_URL  = 'https://apigw.iotbing.com'
```

#### Endpoint regional vs global

O login QR retorna um campo `endpoint` na resposta que indica o servidor regional do usuário (ex: `apigw.tuyaus.com` para América do Sul). Esse endpoint é salvo em `integrations.tuya_endpoint` e **DEVE** ser usado em todas as chamadas do CustomerApi — tanto para listagem quanto para envio de comandos. O `apigw.iotbing.com` (BASE_URL) é apenas o fallback quando nenhum endpoint regional foi retornado.

#### Camada 2 — CustomerApi (chamadas autenticadas pós-login)

Baseado em `tuya_sharing/customerapi.py` — classe `CustomerApi.__request()`.

Protocolo proprietário — **não é HMAC-SHA256**. Por requisição:

1. Gerar `rid` = UUID v4
2. `hash_key = MD5(rid + refresh_token)`
3. `secret = HMAC-SHA256(msg=hash_key, key=rid).hex()[:16]`  (primeiros 16 chars)
4. Params e body cifrados com **AES-128-GCM** usando `secret`; `nonce` de 12 chars aleatórios do alfabeto `ABCDEFGHJKMNPQRSTWXYZabcdefhijkmnprstwxyz2345678`
5. Formato de envio: `{"encdata": base64(nonce) + base64(ciphertext+tag)}`
6. Headers: `X-appKey`, `X-requestId`, `X-sid=""`, `X-time`, `X-token`, `X-sign`
7. `X-sign = HMAC-SHA256(key=hash_key, msg="X-appKey=v||X-requestId=v||X-time=v||X-token=v" + encdata).hexdigest()`
8. `result` da resposta vem cifrado — descriptografar com AES-128-GCM: `base64decode(result)` → nonce=primeiros 12 bytes, tag=últimos 16 bytes, ciphertext=meio

Implementado em PHP no método `customerRequest()`.

#### Camada 3 — Comandos para dispositivos (DPs)

Baseado em `tuya_sharing/device.py` — `DeviceRepository.send_commands()`.

```
POST https://apigw.iotbing.com/v1.1/m/thing/{device_id}/commands
body: {"commands": [{"code": "dp_code", "value": "..."}]}
```

Enviado via `customerRequest()` com body criptografado em AES-GCM.

#### Endpoints usados

```
# Listar homes (autenticado)
GET  /v1.0/m/life/users/homes

# Listar dispositivos de um home (autenticado, params cifrados)
GET  /v1.0/m/life/ha/home/devices?encdata={homeId_cifrado}

# Enviar comando DP para dispositivo (autenticado, body cifrado)
POST /v1.1/m/thing/{device_id}/commands
```

---

### Fechaduras Tuya — DPs de senha temporária

Documentação oficial dos DPs de fechadura:
https://developer.tuya.com/en/docs/iot/zigbee-doorlock-dp?id=K9fembhbeab0p

A fechadura `jtmspro` (e similares) usa DP Raw para criar senhas temporárias.
**Não usa** o endpoint `/v1.0/devices/{id}/door-lock/password-ticket` da OpenAPI.

#### DP `temporary_password_creat` — criar senha

Payload binário de **21 bytes**, codificado em Base64:

| Bytes | Tamanho | Conteúdo |
|-------|---------|----------|
| [0..1]  | 2 | Tuya serial number — uint16 aleatório, big-endian |
| [2..3]  | 2 | Server serial number — uint16 aleatório, big-endian |
| [4..5]  | 2 | Lock manufacturer ID — fixo `0x0000` |
| [6..9]  | 4 | Start time — Unix timestamp, big-endian |
| [10..13]| 4 | End time — Unix timestamp, big-endian |
| [14]    | 1 | One-time flag — `0x00` (não é one-time) |
| [15..20]| 6 | PIN — 6 bytes ASCII do dígito (ex: `"123456"`) |

```php
$bytes = pack('n', $tuyaSeq)       // [0..1]
       . pack('n', $serverSeq)      // [2..3]
       . pack('n', 0)               // [4..5] lock_id fixo
       . pack('N', $effectiveTime)  // [6..9] unix timestamp
       . pack('N', $invalidTime)    // [10..13] unix timestamp
       . chr(0x00)                  // [14] não one-time
       . $pin;                      // [15..20] 6 chars ASCII
$value = base64_encode($bytes);
```

Enviar via `customerRequest()`:
```php
['commands' => [['code' => 'temporary_password_creat', 'value' => $value]]]
```

#### DP `temporary_password_delete` — deletar senha

Payload binário de **6 bytes**, codificado em Base64:

| Bytes | Tamanho | Conteúdo |
|-------|---------|----------|
| [0..1] | 2 | Tuya serial number (mesmo da criação) |
| [2..3] | 2 | Server serial number (mesmo da criação) |
| [4..5] | 2 | Lock manufacturer ID — `0x0000` |

Guardar `tuyaSeq` e `serverSeq` no momento da criação para poder deletar depois.

---

### Campos Tuya na tabela `integrations`

| Coluna | Descrição |
|---|---|
| `tuya_user_code` | User code obtido no app SmartLife |
| `tuya_access_token` | Access token pós-QR login |
| `tuya_refresh_token` | Refresh token — usado na derivação de chave do CustomerApi |
| `tuya_token_expires_at` | Expiração do access token |
| `tuya_uid` | UID do usuário Tuya |
| `tuya_endpoint` | Endpoint retornado pelo login — passado para `customerRequest()` |

---

### Campos que NÃO existem no model `Device`

`external_id`, `user_id`, `type`, `status`, `category`, `online`.
O campo correto de identificação externa é `external_device_id`.

### Enums existentes

- `DeviceBrandEnum`: `portatec`, `tuya`
- `DeviceTypeEnum`: `switch`, `sensor`, `button` — **não existe `Lock`**
- `DeviceStatusEnum`: `open`, `closed`, `on`, `off` — **não existe `Active`**

---

### SDK de referência

**`tuya-device-sharing-sdk`** — Python, MIT, open source.
- Repositório: https://github.com/tuya/tuya-device-sharing-sdk
- PyPI: https://pypi.org/project/tuya-device-sharing-sdk/
- Versão inspecionada: **0.2.1**

Para inspecionar o código fonte:
```bash
pip download tuya-device-sharing-sdk==0.2.1 --no-deps -d /tmp/tuya
cd /tmp/tuya
unzip tuya_device_sharing_sdk-0.2.1-py2.py3-none-any.whl -d sdk_source
# Arquivos relevantes:
# sdk_source/tuya_sharing/user.py        → QR login (LoginControl)
# sdk_source/tuya_sharing/customerapi.py → protocolo autenticado (CustomerApi)
# sdk_source/tuya_sharing/device.py      → comandos e listagem de dispositivos
# sdk_source/tuya_sharing/home.py        → listagem de homes
# sdk_source/tuya_sharing/manager.py     → orquestração geral
```

Mapeamento arquivo SDK → método PHP:

| Arquivo no SDK | Classe/método | Método PHP em `TuyaQrAuthService` |
|---|---|---|
| `user.py` | `LoginControl.qr_code()` | `generateQrCode()` |
| `user.py` | `LoginControl.login_result()` | `pollLogin()` |
| `customerapi.py` | `CustomerApi.__request()` | `customerRequest()` |
| `device.py` | `DeviceRepository.query_devices_by_home()` | `getDevices()` |
| `device.py` | `DeviceRepository.send_commands()` | base para envio de DPs |
| `home.py` | `HomeRepository.query_homes()` | parte de `getDevices()` |
