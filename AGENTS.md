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

## Integração Tuya

Contexto para agentes que alterem ou estendam a integração Tuya.

- **Auth**: Apenas OAuth 2.0; não usar grant_type=1 (implementação antiga ignorada). Config em `config/tuya.php` (`oauth_redirect_uri`, `oauth_authorize_url`, etc.).
- **Dados**: `tuya_credentials` (tokens, uid) por place; `access_code_tuya_passwords` para IDs de senhas temporárias Tuya (sync/delete).
- **Fluxo**: redirect → Tuya → callback → exchange code → save credentials. Client com token injetado; `TuyaClientFactory` por place com refresh; `TuyaService` (getDevices, sendDeviceCommands, sendSwitch, sendPulse, PIN create/delete).
- **Comandos**: `DeviceCommandService` — se dispositivo Tuya e place com credencial, usa factory + TuyaService (pulse/switch); senão MQTT. Sync de access codes: se Tuya, `syncAccessCodesToTuyaLock()` e tabela `access_code_tuya_passwords`.
- **Webhook**: POST `/webhooks/tuya`; controller despacha `ProcessTuyaWebhookJob`; job atualiza `last_sync` e emite `PlaceDeviceFunctionStatusEvent` quando aplicável; rota com CSRF except em `bootstrap/app.php`.
- **Sync dispositivos**: `TuyaDeviceSyncService::syncPlaceDevices(Place)` chama getDevices(uid) e faz updateOrCreate em `Device` (brand Tuya).
- **Paths principais**: `app/Services/Tuya/` (Client, TuyaService, TuyaClientFactory, TuyaDeviceSyncService), `app/Http/Controllers/App/TuyaOAuthController.php`, `app/Http/Controllers/Webhooks/TuyaWebhookController.php`, `app/Jobs/ProcessTuyaWebhookJob.php`, `app/Services/Device/DeviceCommandService.php`, modelos `TuyaCredential`, `AccessCodeTuyaPassword`; testes em `tests/Unit/Tuya*`, `tests/Feature/Tuya*`.
