# Plano detalhado: refatoração da integração Tuya

## Contexto e motivação
A integração Tuya hoje aparece na seção de Integrações pensada para iCal. Isso confunde o usuário e mistura fluxos diferentes. A proposta é separar claramente:

- **iCal**: integração de reservas, deve ficar em **Bookings/Reservas** por place.
- **Tuya**: integração de dispositivos, deve ficar em **Dispositivos**.

Além disso, um **device pode estar vinculado a múltiplos places**, e o Portatec precisa **receber eventos da Tuya** (preferência por webhook, com fallback para polling).

## Objetivos
- Desacoplar completamente Tuya de iCal na UX e na edição.
- Permitir **múltiplas integrações Tuya** por usuário.
- Importar e gerenciar devices Tuya, com **vinculação N:N entre device e place**.
- Exibir e controlar devices por tipo (fechadura, interruptor, sensor, etc.).
- Receber eventos Tuya (push se possível, polling se necessário).

## Escopo
### Inclui
- Nova subpágina de integrações dentro de **Dispositivos**.
- Formulários e telas específicas para Tuya e iCal (sem campos misturados).
- Modelagem e fluxo para múltiplas contas Tuya.
- Sincronização de devices e mapeamento de tipos/capabilities.
- Ingestão de eventos Tuya (webhook/polling).
- UI básica de controle e histórico de eventos.

### Não inclui (por agora)
- Automação avançada baseada em eventos (regras complexas).
- Dashboard analítico de uso de dispositivos.
- Integrações adicionais além de Tuya e iCal.

## Decisões já definidas
- **iCal fica em Reservas/Bookings**.
- **Tuya fica em Dispositivos** (subpágina “Integrações de Dispositivos”).
- **Device pode estar em múltiplos places** (N:N).
- **Eventos Tuya**: tentar webhook; se não possível sem conta dev, usar polling.

## Modelagem de domínio (proposta)
### Entidades principais
- `Integration`
  - `type` (ex: `tuya`, `ical`)
  - `status` (connected, error, disconnected)
  - `metadata` (tokens, region, etc.)
  - `last_sync_at`

- `Device`
  - `name`, `type`, `capabilities`
  - `status` (online/offline)
  - `last_event_at`

- `DeviceIntegration`
  - `device_id`
  - `integration_id`
  - `external_device_id`
  - `raw_metadata`

- `DevicePlace` (N:N)
  - `device_id`
  - `place_id`

- `DeviceEvent`
  - `device_id`
  - `event_type`
  - `payload`
  - `occurred_at`

### Regras
- Um **usuário** pode ter **várias integrações Tuya**.
- Cada integração Tuya pode trazer **N devices**.
- Um **device pode estar vinculado a múltiplos places**.

## Fluxos de UX
### 1. Conectar Tuya
- Acessar Dispositivos → Integrações de Dispositivos → “Conectar Tuya”.
- Fluxo de login via QR code (modelo semelhante ao Home Assistant).
- Estado de conexão e erro visível no card da integração.

### 2. Sincronizar devices
- Após conectar, importar devices da conta.
- Listar devices com:
  - tipo
  - status
  - vinculação a places
- Ação: “Resync” manual.

### 3. Vincular device a places
- Modal/edição do device para selecionar múltiplos places.
- Dispositivo aparece em todos os places vinculados.

### 4. Controle por tipo
- Fechadura: travar/destravar, status atual.
- Interruptor: ligar/desligar.
- Sensor de abertura: aberto/fechado + histórico.

## Ingestão de eventos Tuya
### Opção A: Webhook (preferencial)
- Verificar se o fluxo sem conta developer permite webhook direto.
- Caso permitido:
  - Endpoint HTTPS
  - Verificação de assinatura + decrypt AES
  - Normalização de eventos

### Opção B: Polling (fallback)
- Job periódico que consulta status/eventos via API.
- Persistir mudanças como `DeviceEvent`.
- Usar janela incremental para reduzir carga.

## Migração e compatibilidade
- Remover Tuya da seção de Integrações gerais.
- Mover iCal para Reservas/Bookings.
- Garantir que dados existentes migrem sem perda.
- Manter devices já importados e permitir vinculação múltipla.

## Critérios de aceite
- iCal aparece apenas em Reservas/Bookings com campos específicos.
- Tuya aparece apenas em Dispositivos com campos específicos.
- Múltiplas integrações Tuya por usuário funcionam.
- Devices importados listam tipo, status e places vinculados.
- Um device pode estar em múltiplos places e aparece em todos.
- Eventos entram no sistema via webhook ou polling.

## Plano de execução (fases)
### Fase 1 — UX e estrutura
- Criar subpágina de integrações em Dispositivos.
- Mover iCal para Reservas.
- Separar formulários e edição por integração.

### Fase 2 — Domínio e dados
- Ajustar modelo para N:N entre device e place.
- Estruturar integração Tuya com múltiplas contas.
- Mapear device types e capabilities.

### Fase 3 — Sync e eventos
- Implementar sincronização de devices.
- Implementar ingestão de eventos (webhook ou polling).

### Fase 4 — UI de dispositivos
- Detalhe por tipo com controles.
- Histórico básico de eventos.

## Pendências documentais
- Confirmar oficialmente se o fluxo de QR code (conta do app) permite webhook.
- Se não permitir, definir frequência de polling aceitável.

## Checklist técnico detalhado
### UX e rotas
- [ ] Criar rota `/devices/integrations` com Livewire `Devices/Integrations/Index`.
- [ ] Remover botão Tuya da lista de integrações gerais.
- [ ] Adicionar acesso a integrações de dispositivos a partir da tela de Dispositivos.
- [ ] Garantir que iCal fique visível apenas em Reservas/Bookings.

### Tuya (conexao e contas)
- [ ] Adaptar Tuya Connect para a nova rota em Dispositivos.
- [ ] Permitir multiplas integracoes Tuya por usuario.
- [ ] Exibir status, ultima sincronizacao e erros por integracao.

### Modelagem (Device x Place)
- [ ] Criar pivot `device_place` (N:N).
- [ ] Atualizar relacoes em `Device` e `Place`.
- [ ] Atualizar queries e filtros que assumem `place_id` unico.
- [ ] Ajustar telas para selecionar multiplos places para um device.

### Sync e eventos
- [ ] Implementar sincronizacao inicial e resync manual.
- [ ] Criar pipeline de ingestao de eventos Tuya.
- [ ] Se webhook indisponivel, criar job de polling.

### UI de dispositivos
- [ ] Exibir dispositivos por tipo com controles basicos.
- [ ] Exibir historico resumido de eventos.
