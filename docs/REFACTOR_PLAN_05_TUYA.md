# PLANO DE REFATORAÇÃO — MELHORIAS NA COMUNICAÇÃO TUYA

Este documento detalha as melhorias na integração com Tuya.

---

## 1. CRIAR WEBHOOK PARA EVENTOS TUYA

### 1.1 Rota

**Arquivo**: `routes/api.php`

```php
Route::post('/webhooks/tuya', [TuyaWebhookController::class, 'handle'])
    ->name('webhooks.tuya');
```

### 1.2 Controller

**Arquivo**: `app/Http/Controllers/Webhook/TuyaWebhookController.php`

### 1.3 Responsabilidades

- Receber eventos da Tuya
- Validar assinatura (se aplicável)
- Normalizar dados
- Criar eventos unificados

---

## 2. CRIAR NORMALIZADOR DE EVENTOS

### 2.1 Arquivo

**Arquivo**: `app/Services/Tuya/EventNormalizer.php`

### 2.2 Responsabilidades

- Converter eventos Tuya para formato interno
- Criar `AccessEvent` quando houver tentativa de acesso
- Criar `DeviceStatusEvent` quando houver mudança de status

### 2.3 Implementação

```php
public function normalize(array $tuyaEvent): array
{
    // Converter formato Tuya para formato Portatec
    return [
        'device_id' => $this->findDeviceByTuyaId($tuyaEvent['device_id']),
        'event_type' => $this->mapEventType($tuyaEvent['type']),
        'data' => $this->mapData($tuyaEvent['data']),
    ];
}
```

---

## 3. ATUALIZAR TUYASERVICE

### 3.1 Melhorias

- Método para enviar comandos de abertura
- Método para obter status do dispositivo
- Tratamento de erros melhorado
- Retry logic
- Logging

### 3.2 Métodos

```php
public function sendOpenCommand(string $tuyaDeviceId): bool
{
    // Enviar comando de abertura
}

public function getDeviceStatus(string $tuyaDeviceId): array
{
    // Obter status atual
}
```

---

## 4. CHECKLIST

- [ ] Criar rota webhook
- [ ] Criar TuyaWebhookController
- [ ] Criar EventNormalizer
- [ ] Atualizar TuyaService
- [ ] Implementar validação de assinatura
- [ ] Testar webhook
- [ ] Testar normalização
- [ ] Documentar API Tuya
