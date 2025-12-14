# REVISÃO DO PLANO DE REFATORAÇÃO

Este documento lista problemas encontrados, inconsistências e itens faltantes no plano de refatoração.

---

## ✅ VERIFICAÇÕES REALIZADAS

### Campos e Nomenclaturas
- ✅ `chip_id` → `external_device_id` (atualizado em todos os documentos)
- ✅ `integration_type` → `brand` (atualizado em todos os documentos)
- ✅ `tuya_device_id` removido (atualizado em todos os documentos)
- ✅ `functional_type` removido do Device (atualizado)
- ✅ `status` removido de Booking (atualizado)
- ✅ Switch removido (atualizado)

### Relacionamentos
- ✅ Booking usa `integration_id` (não `platform_id`)
- ✅ `external_id` movido de Integration para `place_integration`
- ✅ Place-Integration é many-to-many com `external_id` na pivot

### Estruturas
- ✅ Device pode ter múltiplas DeviceFunctions
- ✅ DeviceFunction.type usa DeviceTypeEnum (Button, Sensor)
- ✅ Booking tem soft deletes e deletion_reason

---

## ⚠️ PROBLEMAS ENCONTRADOS

### 1. REFACTOR_PLAN.md - Descrição Incorreta

**Problema**: Ainda menciona "Criar Resource Filament para acionar dispositivos" mas na verdade é uma view Livewire.

**Localização**:
- Linha 44: "**Criar Resource Filament para acionar dispositivos**"
- Linha 94: "2. **Criar Resource Filament para acionar dispositivos**"

**Correção**: Atualizar para "Criar view Livewire para acionar dispositivos"

---

### 2. AccessCode.user_id - Nullable ou Não?

**Problema**:
- MIGRATION_PLAN.md diz: "Ensure `place_id` and `user_id` (nullable) are present"
- Migration atual de `access_pins` tem `user_id` como NOT NULL
- BookingObserver cria AccessCode com `user_id` nullable (`$booking->integration?->user_id`)

**Ação Necessária**:
- Decidir se `user_id` deve ser nullable em AccessCode
- Se sim, criar migration para tornar nullable
- Se não, ajustar BookingObserver para sempre fornecer user_id

**Recomendação**: Tornar `user_id` nullable, pois AccessCodes criados a partir de Bookings podem não ter user_id direto.

---

### 3. REFACTOR_PLAN_04_BOOKINGS.md - Falta Import Http

**Problema**: O método `downloadICal` usa `Http::get()` mas não há import.

**Localização**: Linha 182

**Correção**: Adicionar `use Illuminate\Support\Facades\Http;` no topo do arquivo de serviço.

---

### 4. REFACTOR_PLAN_01_MODELS_AND_DATABASE.md - Migration de Renomear Coluna

**Problema**: A migration tenta renomear `access_pin_id` para `access_code_id`, mas essa coluna pode não existir na tabela `access_codes` (ela está em outras tabelas que referenciam access_codes).

**Localização**: Linha 34

**Ação Necessária**:
- Verificar se há foreign keys em outras tabelas que referenciam `access_pins`
- Criar migrations separadas para renomear foreign keys em outras tabelas
- A migration de renomear tabela não deve tentar renomear colunas que não existem nela

---

### 5. REFACTOR_PLAN_04_BOOKINGS.md - Lógica de Soft Delete

**Problema**: No método `createOrUpdateBooking`, quando há mudanças, o código faz:
```php
$booking->delete();
$booking->deletion_reason = $deletionReason;
$booking->save();
```

Isso está incorreto. Após `delete()`, o modelo está "deleted" e não pode ser salvo normalmente.

**Correção**: Deve ser:
```php
$booking->deletion_reason = $deletionReason;
$booking->delete();
```

Ou usar `forceDelete()` se não quiser soft delete, mas o plano especifica soft delete.

---

### 6. REFACTOR_PLAN_01_MODELS_AND_DATABASE.md - AccessCode.user_id

**Problema**: Não há menção explícita sobre tornar `user_id` nullable em AccessCode após adicionar `booking_id`.

**Ação Necessária**:
- Adicionar migration para tornar `user_id` nullable em `access_codes`
- Atualizar model AccessCode para refletir isso
- Documentar que `user_id` pode ser null quando AccessCode é criado a partir de Booking

---

### 7. REFACTOR_PLAN_04_BOOKINGS.md - Método syncIntegration

**Problema**: O método `syncIntegration` no ICalSyncService itera sobre `$integration->places`, mas não há verificação se a Integration tem Places relacionados.

**Ação Necessária**: Adicionar verificação e tratamento de erro se não houver Places.

---

### 8. REFACTOR_PLAN_02_SYNC_ACCESS_CODES.md - AccessCodeObserver

**Problema**: O observer chama métodos estáticos do serviço, mas deveria usar injeção de dependência.

**Correção**:
```php
public function __construct(
    private AccessCodeSyncService $syncService
) {}

public function created(AccessCode $accessCode): void
{
    $this->syncService->syncNewAccessCode($accessCode);
}
```

---

### 9. REFACTOR_PLAN_01_MODELS_AND_DATABASE.md - Place.placeDeviceFunctions

**Problema**: O modelo Place já tem `placeDeviceFunctions()` mas não está documentado que será mantido.

**Ação Necessária**: Documentar que este relacionamento será mantido e usado na view Livewire.

---

### 10. REFACTOR_PLAN_06_UI_FILAMENT.md - Rota Livewire

**Problema**: A rota criada é uma rota web normal, mas componentes Livewire geralmente são acessados via rotas Livewire ou dentro de rotas Filament.

**Ação Necessária**: Verificar se a rota deve ser:
- Rota web simples: `Route::get('/places/{place}/devices', ...)`
- Ou rota dentro do contexto Filament

**Recomendação**: Manter como rota web simples, mas garantir que o middleware de autenticação do Filament seja aplicado.

---

## 📋 ITENS FALTANTES

### 1. Migration para tornar user_id nullable em access_codes

**Arquivo**: `database/migrations/XXXX_XX_XX_XXXXXX_make_user_id_nullable_in_access_codes.php`

```php
Schema::table('access_codes', function (Blueprint $table) {
    $table->foreignId('user_id')->nullable()->change();
});
```

### 2. Documentação sobre PlaceDeviceFunction

Adicionar nota explicando que:
- `PlaceDeviceFunction` é a tabela pivot que relaciona Places com DeviceFunctions
- Será mantida e usada na view Livewire
- Um Place pode ter múltiplas DeviceFunctions de múltiplos Devices

### 3. Atualização do AccessCodeResource (Filament)

Documentar que:
- `user_id` deve ser opcional no form
- Adicionar campo para mostrar Booking relacionado
- Atualizar validação se necessário

### 4. Service Provider para ICalParserInterface

Documentar que será necessário registrar a implementação concreta do `ICalParserInterface` no service container:

```php
// AppServiceProvider ou ServiceProvider dedicado
$this->app->bind(ICalParserInterface::class, ICalParser::class);
```

### 5. Middleware para rota Livewire

Documentar que a rota `places.devices` deve ter:
- Middleware de autenticação
- Verificação de permissões (similar ao que está no componente)

---

## 🔍 VERIFICAÇÕES ADICIONAIS NECESSÁRIAS

### 1. Verificar Foreign Keys

Buscar todas as tabelas que referenciam `access_pins`:
```bash
grep -r "access_pin" database/migrations/
```

Tabelas comuns que podem ter foreign keys:
- `command_logs` (se existir)
- Outras tabelas que referenciam access_pins

### 2. Verificar Observers Existentes

Verificar se há outros observers que usam AccessPin e precisam ser atualizados.

### 3. Verificar Policies

Verificar se há policies que referenciam AccessPin e precisam ser atualizadas.

### 4. Verificar Events

Verificar se há outros events além de AccessPinEvent que precisam ser atualizados.

### 5. Verificar Factories e Seeders

Verificar se há factories e seeders que usam AccessPin e precisam ser atualizados.

---

## ✅ CHECKLIST DE CORREÇÕES

- [x] Atualizar REFACTOR_PLAN.md (remover "Resource Filament", adicionar "view Livewire")
- [x] Decidir sobre user_id nullable em AccessCode e criar migration (tornar nullable)
- [x] Adicionar import Http no ICalSyncService
- [x] Corrigir lógica de soft delete no createOrUpdateBooking (deletion_reason antes de delete)
- [x] Adicionar verificação de Places em syncPlaceIntegration
- [x] Atualizar AccessCodeObserver para usar injeção de dependência
- [x] Documentar PlaceDeviceFunction (mantido para uso na view Livewire)
- [x] Adicionar documentação sobre registro do ICalParserInterface
- [ ] Verificar e atualizar foreign keys em outras tabelas (ação manual necessária)
- [ ] Verificar observers, policies, events, factories, seeders (ação manual necessária)

---

## 📝 NOTAS FINAIS

O plano está bem estruturado e a maioria das mudanças está documentada corretamente. Os problemas encontrados são principalmente:
1. Pequenas inconsistências de nomenclatura
2. Detalhes de implementação que precisam ser refinados
3. Algumas verificações adicionais necessárias

Após corrigir estes pontos, o plano estará pronto para implementação.
