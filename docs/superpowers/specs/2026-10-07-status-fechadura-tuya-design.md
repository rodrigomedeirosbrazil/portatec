# Design: status da fechadura Tuya

Data: 2026-10-07

Mostra no PortaTec o estado da fechadura Tuya — trancada/destrancada, bateria e alerta — e o
mantém atualizado em tempo real pelo MQTT que já roda em produção.

Não envia comando. A investigação que motivou esta spec (AGENTS.md §11.6.1) mostrou que o canal
de device-sharing **recusa** abrir e trancar fechadura (`[2008]`) e não cria senha temporária
(§11.6). O que ele entrega é status: a fechadura reporta o motor, a bateria e os alarmes pelo
MQTT em ~2 s. Esta spec aproveita isso.

---

## 1. Problemas no código atual

1. **O status é sobrescrito, não mesclado.** `TuyaMqttService::handleDeviceReport` grava em
   `devices.tuya_status_payload` só o `data.status` do evento recebido. Cada evento MQTT traz
   apenas os DPs que mudaram (§11.5), então um evento com `lock_motor_state` apaga a bateria e
   os alarmes que estavam lá.
2. **Dado sensível é persistido.** O DP 71 `ble_unlock_check` carrega em claro o código de
   verificação que abre a fechadura por Bluetooth. Ele vem no `status` do `/devices/detail` e nos
   eventos MQTT, e hoje vai direto para `tuya_status_payload`.
3. **A tela não atualiza sozinha.** Eventos Tuya não disparam broadcast. Nem o selo
   online/offline (`tuya_online`) muda sem recarregar a página.
4. **A mensagem da tela de controle está errada para a maioria das fechaduras.** Para qualquer
   fechadura Tuya ela diz que a fechadura "recebe os PINs temporários pelos Access Codes deste
   local" — o que só vale quando `supportsTuyaTemporaryPassword()` é verdadeiro.

---

## 2. Escopo

**Dentro:**

- mesclar o status por `code` e nunca persistir os DPs sensíveis;
- limpar os DPs sensíveis já gravados;
- derivar o status da fechadura num objeto de valor;
- broadcast do status e da mudança online/offline;
- exibir o status nas telas de controle (local e dispositivo) e no detalhe do dispositivo.

**Fora:** histórico de eventos, notificação (bateria baixa, arrombamento), comandos de abrir ou
trancar, colunas novas em `devices`, filtro de fechaduras por estado.

---

## 3. Persistência — `TuyaStatusPayload`

Classe nova em `app/Services/Tuya/TuyaStatusPayload.php`. É a regra única de como o status Tuya
é gravado. O formato persistido não muda: lista de `{code, value, t}`.

```php
TuyaStatusPayload::merge(array $current, array $incoming): array
```

Regras, nesta ordem, para cada entrada de `$incoming`:

1. Sem `code` string não vazia → descarta. São os DPs não declarados que chegam só com chave
   numérica, como `{"46": true}` (§11.5).
2. `code` em `SENSITIVE_CODES = ['ble_unlock_check', 'check_code_set']` → descarta.
3. `t` presente no evento → usa esse `t` (ms).
4. `t` ausente (o snapshot do `/devices/detail` não traz) → se o `value` é igual ao atual, mantém
   o `t` atual; se mudou, usa `now()` em ms; se não havia entrada, `t = null`.
5. Substitui a entrada de mesmo `code` em `$current`, ou acrescenta.

Entradas de `$current` cujo `code` é sensível também saem no resultado — assim qualquer gravação
limpa o que tiver sobrado. Campos extras do evento (a chave numérica duplicada, `dpId`) não são
guardados.

Os três pontos que gravam o payload passam a usar `merge`:

| Onde | Hoje |
|---|---|
| `TuyaMqttService::handleDeviceReport` | substitui pelo `data.status` |
| `TuyaIntegrationService::refreshDeviceSnapshot` | substitui pelo `status` do snapshot |
| `TuyaConnectController` (importação) | grava o `status` do snapshot |

Na importação `$current` é `[]`, então `merge` só filtra.

### Migration de limpeza

Uma migration remove `ble_unlock_check` e `check_code_set` de `tuya_status_payload` em todas as
linhas de `devices` com `brand = tuya`, usando `TuyaStatusPayload::merge($payload, [])`. O
`down()` não restaura nada: o dado não deveria existir.

---

## 4. Derivação — `TuyaLockStatus`

Objeto de valor `readonly` em `app/Services/Tuya/DTOs/TuyaLockStatus.php`.

```php
TuyaLockStatus::fromDevice(Device $device): ?TuyaLockStatus   // null se !isTuyaLock()
$status->toArray(): array
```

| Campo | Origem | Regra |
|---|---|---|
| `locked: ?bool` | `lock_motor_state` (DP 47) | O DP é `true` quando o motor está **aberto**, então `locked = ! value`. Sem o DP → `null`. |
| `battery: ?int` | `residual_electricity` (DP 8) | `0..100`. `-1`, ausente ou fora da faixa → `null`. |
| `alert: ?string` | `alarm_lock` (DP 21) | Só se o valor estiver em `RELEVANT_ALERTS` **e** o `t` estiver nas últimas 24 h. Sem `t` → `null`. |
| `updatedAt: ?CarbonImmutable` | `t` de `lock_motor_state` | `null` se não houver `t`. |

`RELEVANT_ALERTS = ['pry', 'shock', 'low_battery', 'power_off', 'too_hot', 'unclosed_time',
'tongue_bad', 'tongue_not_out']`. Ficam de fora `wrong_finger`, `wrong_password`, `wrong_card`,
`wrong_face` e `key_in`: são tentativas comuns e virariam ruído permanente na tela.

A inversão do DP 47 foi observada no hardware: abrir pela fechadura ou pelo app reporta `true`,
trancar reporta `false`.

`toArray()` devolve `{locked, battery, alert, updated_at}` com `updated_at` em ISO 8601. É o
formato das props e do broadcast.

Atalho no model: `Device::tuyaLockStatus(): ?TuyaLockStatus`.

---

## 5. Tempo real

### `PlaceTuyaLockStatusEvent`

```php
new PlaceTuyaLockStatusEvent(int $placeId, int $deviceId, array $status)
```

- `ShouldBroadcast`, canal privado `Place.Device.Status.{placeId}` (o mesmo do
  `PlaceDeviceStatusEvent`, já autorizado), `broadcastAs` = `PlaceTuyaLockStatus`.
- `$status` é `TuyaLockStatus::toArray()`.

Disparado em `handleDeviceReport` quando o dispositivo é fechadura **e** o `toArray()` depois do
merge difere do de antes. Eventos repetidos — o mesmo relatório em `/sta` e `/pen`, a bateria
reportada várias vezes — não geram broadcast.

Um dispositivo pode estar em vários locais: o evento vai para cada `place_id` de
`$device->places`, mais `$device->place_id` se não estiver entre eles.

### Online/offline

`handleBizEvent` passa a disparar o `PlaceDeviceStatusEvent` existente, para cada local, quando
`tuya_online` muda de valor. Vale para qualquer dispositivo Tuya, não só fechadura.

Os dois eventos vão pela fila (Horizon) e não bloqueiam o loop do `tuya:subscribe`.

---

## 6. Front

### Hook `useTuyaLockStatus`

`resources/js/hooks/use-tuya-lock-status.ts`, separado do `useDeviceCommands` (que é a máquina de
comandos e não tem relação com isto).

```ts
useTuyaLockStatus({ placeId, initial }: {
    placeId: number | null;
    initial: Record<DeviceId, TuyaLockStatus>;
}): { get(deviceId: DeviceId): TuyaLockStatus | null }
```

Assina `.PlaceTuyaLockStatus` em `Place.Device.Status.{placeId}` e substitui o status do
`deviceId` recebido. O estado fica num reducer puro (`tuya-lock-status-reducer.ts`), testável
como o `device-commands-reducer.ts`.

### Componente `TuyaLockStatusPanel`

- Selo: **Trancada** (`success`), **Destrancada** (`warning`), **Estado desconhecido**
  (`neutral`).
- Bateria em %, com destaque abaixo de 20%. Sem bateria → não exibe a linha.
- Alerta, quando houver, com o texto traduzido do alarme.
- "Atualizado há X" a partir de `updated_at`. Sem `updated_at` → não exibe.

### Onde entra

| Tela | Controller | Mudança |
|---|---|---|
| Controle do local | `PlaceControlController` | `lock_status` por dispositivo; o painel substitui `device_control_tuya_lock_message` |
| Controle do dispositivo | `DeviceControlController` | idem |
| Detalhe do dispositivo | `DeviceController::show` | `lock_status` + `placeId` nas props; painel na seção do dispositivo |

A mensagem sobre PINs por Access Codes passa a aparecer só quando
`supports_tuya_temporary_password` for verdadeiro, e como texto complementar abaixo do painel.

### i18n

Chaves novas em `resources/lang/pt_BR/app.php`: os três estados, o rótulo de bateria, o
"atualizado há", e um texto por item de `RELEVANT_ALERTS`.

---

## 7. Testes

**Unitários (PHPUnit):**

- `TuyaStatusPayloadTest`: mescla por `code`; descarta entrada sem `code`; descarta e remove
  `ble_unlock_check` e `check_code_set` (inclusive de `$current`); regra do `t` (vindo do evento,
  valor igual mantém, valor diferente usa agora, entrada nova sem `t` fica `null`).
- `TuyaLockStatusTest`: `null` para não-fechadura; inversão do DP 47; DP 47 ausente → `null`;
  bateria `-1` → `null`; alerta relevante dentro de 24 h; alerta fora de 24 h; alerta sem `t`;
  alerta irrelevante (`wrong_finger`).

**`TuyaMqttServiceTest`:**

- evento parcial preserva os demais DPs;
- evento com DP 71 não grava o DP 71;
- com `Event::fake`: broadcast só quando o status muda; não sai para dispositivo que não é
  fechadura; sai uma vez por local;
- `online`/`offline` dispara `PlaceDeviceStatusEvent` só quando `tuya_online` muda.

**Feature:**

- telas de controle (local e dispositivo) e detalhe entregam `lock_status` para fechadura Tuya e
  não entregam para os demais;
- a migration de limpeza remove o DP 71 e preserva os outros DPs.

**Vitest:** `tuya-lock-status-reducer.test.ts` — estado inicial, atualização de um dispositivo,
evento de dispositivo desconhecido.

---

## 8. Riscos

- **Status parado sem MQTT.** Se o `tuya-subscriber` estiver reiniciando em ciclo, o status fica
  no último valor. O "atualizado há X" deixa isso visível; corrigir o processo é outro assunto
  (AGENTS.md §9: o healthcheck não cobre esse processo).
- **Volume de broadcast.** Limitado pelo "só quando mudou" e pelo número de fechaduras; uma
  fechadura gera poucos eventos por acionamento.
- **Fechadura de outro modelo.** Os códigos usados (`lock_motor_state`, `residual_electricity`,
  `alarm_lock`) são do padrão Tuya de fechadura, mas um modelo pode não expor algum deles. Cada
  campo tem `null` como estado válido e a UI degrada para "Estado desconhecido" ou omite a linha.
