# PLANO DE REFATORAÇÃO — INTEGRAÇÃO COM BOOKINGS

Este documento detalha a integração com bookings e sincronização iCal.

---

## 1. CRIAR OBSERVER PARA BOOKING

### 1.1 Arquivo

**Arquivo**: `app/Observers/BookingObserver.php`

### 1.2 Ações

#### created
- Criar AccessCode automaticamente
- PIN gerado automaticamente (6 dígitos)
- `start` = `check_in`
- `end` = `check_out`
- `booking_id` = booking.id
- `place_id` = booking.place_id

#### updated
- Atualizar AccessCode se `check_in`/`check_out` mudarem
- Se AccessCode não existir, criar

#### deleted
- Deletar AccessCode associado

### 1.3 Implementação

```php
public function created(Booking $booking): void
{
    if ($booking->status === 'confirmed') {
        $pin = $this->generateUniquePin($booking->place_id);

        AccessCode::create([
            'place_id' => $booking->place_id,
            'booking_id' => $booking->id,
            'pin' => $pin,
            'start' => $booking->check_in,
            'end' => $booking->check_out,
            'user_id' => $booking->integration?->user_id,
        ]);
    }
}

private function generateUniquePin(int $placeId): string
{
    do {
        $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    } while (AccessCode::where('place_id', $placeId)
        ->where('pin', $pin)
        ->where(function ($query) {
            $query->where('start', '<=', now())
                  ->where('end', '>=', now());
        })
        ->exists());

    return $pin;
}
```

---

## 2. CRIAR SERVIÇO DE SINCRONIZAÇÃO ICAL

### 2.1 Arquivo

**Arquivo**: `app/Services/ICalSyncService.php`

### 2.2 Dependências

```bash
composer require kigkonsult/icalcreator
```

### 2.3 Responsabilidades

- Baixar arquivo iCal da URL (de `Integration.external_id`)
- Parsear eventos
- Criar/atualizar Bookings
- Associar com Integration
- Tratar erros e logs

### 2.4 Métodos

```php
public function syncIntegration(Integration $integration): void
{
    // Baixar iCal de $integration->external_id
    // Parsear
    // Criar/atualizar bookings associados a esta integration
}

public function parseICal(string $icalContent): array
{
    // Retorna array de eventos
}

private function createOrUpdateBooking(array $event, Integration $integration): Booking
{
    // Cria ou atualiza booking baseado em external_id do evento iCal
    // Associa com $integration
}
```

---

## 3. CRIAR COMANDO DE SINCRONIZAÇÃO

### 3.1 Arquivo

**Arquivo**: `app/Console/Commands/SyncICalBookingsCommand.php`

### 3.2 Uso

```bash
php artisan bookings:sync-ical
php artisan bookings:sync-ical --integration=1
```

### 3.3 Agendamento

**Arquivo**: `app/Console/Kernel.php`

```php
$schedule->command('bookings:sync-ical')
    ->hourly();
```

---

## 4. CHECKLIST

- [ ] Criar BookingObserver
- [ ] Implementar criação automática de AccessCode
- [ ] Instalar biblioteca iCal
- [ ] Criar ICalSyncService
- [ ] Criar comando SyncICalBookingsCommand
- [ ] Agendar comando
- [ ] Testar sincronização
- [ ] Testar criação de AccessCode
- [ ] Tratar erros e edge cases
