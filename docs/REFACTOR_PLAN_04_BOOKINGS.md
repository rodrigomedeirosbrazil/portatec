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
    // Criar AccessCode para todos os bookings
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

## 2. CRIAR DTO PARA BOOKING

### 2.1 Arquivo

**Arquivo**: `app/DTOs/BookingDTO.php`

### 2.2 Estrutura

```php
<?php

declare(strict_types=1);

namespace App\DTOs;

use Carbon\CarbonInterface;

class BookingDTO
{
    public function __construct(
        public string $externalId,
        public string $guestName,
        public CarbonInterface $checkIn,
        public CarbonInterface $checkOut,
    ) {}
}
```

**Nota**: Esta classe será retornada pelo parser de iCal fornecido externamente.

---

## 3. CRIAR SERVIÇO DE SINCRONIZAÇÃO ICAL

### 3.1 Arquivo

**Arquivo**: `app/Services/ICalSyncService.php`

### 3.2 Dependências

**Nota**: Não será instalada biblioteca externa. O parser de iCal será fornecido como uma classe injetada via dependency injection.

### 3.3 Responsabilidades

- Conectar via HTTP na URL do iCal (de `place_integration.external_id`)
- Usar parser fornecido para parsear o conteúdo iCal
- Receber Collection de `BookingDTO`
- Criar/atualizar Bookings para o Place específico
- Fazer soft delete de Bookings que não estão mais no iCal (com reason)
- Associar com Integration
- Tratar erros e logs

### 3.4 Estrutura do Serviço

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\BookingDTO;
use App\Models\Integration;
use App\Models\Place;
use App\Models\Booking;
use App\Enums\BookingDeletionReasonEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ICalSyncService
{
    public function __construct(
        private ICalParserInterface $parser
    ) {}

    public function syncPlaceIntegration(int $placeId, int $integrationId): void
    {
        // Sincronizar um relacionamento específico Place-Integration
        $place = Place::findOrFail($placeId);
        $integration = Integration::findOrFail($integrationId);

        $placeIntegration = $place->integrations()
            ->where('integration_id', $integrationId)
            ->first();

        if (!$placeIntegration) {
            throw new \Exception("Place-Integration relationship not found");
        }

        $externalId = $placeIntegration->pivot->external_id;

        // Baixar iCal via HTTP
        $icalContent = $this->downloadICal($externalId);

        // Parsear usando a classe fornecida
        $bookingDTOs = $this->parser->parse($icalContent);

        // Obter bookings existentes para este relacionamento
        $existingBookings = Booking::where('place_id', $placeId)
            ->where('integration_id', $integrationId)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('external_id');

        // Criar/atualizar bookings
        $currentExternalIds = [];
        foreach ($bookingDTOs as $bookingDTO) {
            $currentExternalIds[] = $bookingDTO->externalId;
            $this->createOrUpdateBooking($bookingDTO, $integration, $place, $existingBookings);
        }

        // Soft delete bookings que não estão mais no iCal
        $removedBookings = $existingBookings->whereNotIn('external_id', $currentExternalIds);
        foreach ($removedBookings as $booking) {
            $booking->deletion_reason = BookingDeletionReasonEnum::Canceled;
            $booking->delete();
        }
    }

    private function downloadICal(string $url): string
    {
        // Fazer requisição HTTP para baixar o iCal
        $response = Http::get($url);

        if (!$response->successful()) {
            throw new \Exception("Failed to download iCal from: {$url}");
        }

        return $response->body();
    }

    private function createOrUpdateBooking(
        BookingDTO $bookingDTO,
        Integration $integration,
        Place $place,
        Collection $existingBookings
    ): Booking {
        $booking = $existingBookings->get($bookingDTO->externalId);

        if ($booking) {
            // Verificar se houve mudanças
            $hasChanges = false;
            $deletionReason = null;

            if ($booking->check_in->format('Y-m-d H:i:s') !== $bookingDTO->checkIn->format('Y-m-d H:i:s') ||
                $booking->check_out->format('Y-m-d H:i:s') !== $bookingDTO->checkOut->format('Y-m-d H:i:s')) {
                $hasChanges = true;
                $deletionReason = BookingDeletionReasonEnum::ChangeDate;
            }

            if ($booking->guest_name !== $bookingDTO->guestName) {
                $hasChanges = true;
                $deletionReason = BookingDeletionReasonEnum::ChangeGuest;
            }

            if ($hasChanges) {
                // Soft delete o booking antigo com reason
                $booking->deletion_reason = $deletionReason;
                $booking->delete();

                // Criar novo booking
                return Booking::create([
                    'place_id' => $place->id,
                    'integration_id' => $integration->id,
                    'external_id' => $bookingDTO->externalId,
                    'guest_name' => $bookingDTO->guestName,
                    'check_in' => $bookingDTO->checkIn,
                    'check_out' => $bookingDTO->checkOut,
                ]);
            }

            // Sem mudanças, apenas atualizar se necessário
            $booking->update([
                'guest_name' => $bookingDTO->guestName,
                'check_in' => $bookingDTO->checkIn,
                'check_out' => $bookingDTO->checkOut,
            ]);

            return $booking;
        }

        // Criar novo booking
        return Booking::create([
            'place_id' => $place->id,
            'integration_id' => $integration->id,
            'external_id' => $bookingDTO->externalId,
            'guest_name' => $bookingDTO->guestName,
            'check_in' => $bookingDTO->checkIn,
            'check_out' => $bookingDTO->checkOut,
        ]);
    }
}
```

### 3.5 Interface do Parser

**Arquivo**: `app/Contracts/ICalParserInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\BookingDTO;
use Illuminate\Support\Collection;

interface ICalParserInterface
{
    /**
     * Parse iCal content and return Collection of BookingDTO
     */
    public function parse(string $icalContent): Collection;
}
```

**Nota**: A classe concreta que implementa esta interface será fornecida externamente e registrada no service container.

**Registro no Service Container**:

**Arquivo**: `app/Providers/AppServiceProvider.php` (ou ServiceProvider dedicado)

```php
use App\Contracts\ICalParserInterface;

public function register(): void
{
    // Registrar implementação concreta do parser
    // A classe concreta será fornecida externamente
    $this->app->bind(ICalParserInterface::class, ICalParser::class);
}
```

---

## 4. CRIAR JOB PARA SINCRONIZAÇÃO

### 4.1 Arquivo

**Arquivo**: `app/Jobs/SyncIntegrationBookingsJob.php`

### 4.2 Responsabilidades

- Processar sincronização de uma Integration específica
- Para cada Place relacionado, chamar `ICalSyncService::syncPlaceIntegration()`
- Tratar erros e fazer retry se necessário

### 4.3 Implementação

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Integration;
use App\Services\ICalSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncIntegrationBookingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $integrationId
    ) {}

    public function handle(ICalSyncService $syncService): void
    {
        $integration = Integration::findOrFail($this->integrationId);

        // Para cada Place relacionado à Integration
        foreach ($integration->places as $place) {
            try {
                $syncService->syncPlaceIntegration($place->id, $integration->id);
            } catch (\Exception $e) {
                // Log erro e continuar com próximo Place
                \Log::error("Failed to sync bookings for Place {$place->id} and Integration {$integration->id}: " . $e->getMessage());
            }
        }
    }
}
```

---

## 5. CRIAR COMANDO DE SINCRONIZAÇÃO

### 5.1 Arquivo

**Arquivo**: `app/Console/Commands/SyncBookingsCommand.php`

### 5.2 Uso

```bash
# Sincronizar todas as integrações ativas
php artisan bookings:sync

# Sincronizar por plataforma
php artisan bookings:sync --platform=airbnb

# Sincronizar por integration_id
php artisan bookings:sync --integration=1
```

### 5.3 Implementação

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncIntegrationBookingsJob;
use App\Models\Integration;
use App\Models\Platform;
use Illuminate\Console\Command;

class SyncBookingsCommand extends Command
{
    protected $signature = 'bookings:sync
                            {--platform= : Platform slug (e.g., airbnb, booking_com)}
                            {--integration= : Integration ID}';

    protected $description = 'Synchronize bookings from iCal for active integrations';

    public function handle(): int
    {
        $query = Integration::query()
            ->whereNull('deleted_at')
            ->with('places');

        if ($this->option('platform')) {
            $platform = Platform::where('slug', $this->option('platform'))->first();
            if (!$platform) {
                $this->error("Platform not found: {$this->option('platform')}");
                return Command::FAILURE;
            }
            $query->where('platform_id', $platform->id);
        }

        if ($this->option('integration')) {
            $query->where('id', $this->option('integration'));
        }

        $integrations = $query->get();

        if ($integrations->isEmpty()) {
            $this->info('No active integrations found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$integrations->count()} integration(s) to sync.");

        foreach ($integrations as $integration) {
            SyncIntegrationBookingsJob::dispatch($integration->id);
            $this->line("Queued sync job for Integration ID: {$integration->id}");
        }

        $this->info('All sync jobs have been queued.');

        return Command::SUCCESS;
    }
}
```

### 5.4 Agendamento

**Arquivo**: `app/Console/Kernel.php`

```php
$schedule->command('bookings:sync')
    ->everyThreeHours();
```

---

## 6. CHECKLIST

- [ ] Criar BookingObserver
- [ ] Implementar criação automática de AccessCode
- [ ] Criar BookingDTO
- [ ] Criar ICalParserInterface
- [ ] Registrar implementação concreta do ICalParserInterface no service container
- [ ] Criar ICalSyncService
- [ ] Implementar download de iCal via HTTP (usar Http facade)
- [ ] Implementar lógica de create/update com soft delete
- [ ] Implementar detecção de mudanças (date, guest)
- [ ] Corrigir ordem de soft delete (deletion_reason antes de delete())
- [ ] Criar SyncIntegrationBookingsJob
- [ ] Criar comando SyncBookingsCommand
- [ ] Agendar comando para executar a cada 3 horas (everyThreeHours)
- [ ] Testar sincronização
- [ ] Testar criação de AccessCode
- [ ] Testar soft delete com reason
- [ ] Tratar erros e edge cases
