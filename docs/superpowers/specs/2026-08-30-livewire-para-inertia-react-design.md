> [!IMPORTANT]
> **ESTE DOCUMENTO DEVE SER REMOVIDO ANTES DO MERGE.**
> Ele existe apenas para orientar a implementacao nesta branch
> (`claude/livewire-react-inertia-refactor-ab6bed`). O ultimo commit antes
> de abrir/mergear o PR deve apagar `docs/superpowers/specs/` desta branch.

# Migração do app do cliente: Livewire → Inertia 2 + React

- **Data:** 2026-08-30
- **Status:** aprovado (design), pendente plano de implementação
- **Escopo:** app do cliente (`/app/*`). O painel Filament (`/admin`) não é tocado.

---

## 1. Contexto e problema

O app do cliente é hoje Livewire 3 + Blade: 25 componentes em `app/Livewire`
(~2.4k LOC PHP) e 22 views em `resources/views/livewire`. A manutenção do front
está custosa e implementar controle JavaScript é desproporcionalmente difícil.

O caso mais representativo é `resources/views/livewire/places/control.blade.php`:
uma máquina de estados de ~100 linhas de Alpine embutida no template, com
`@foreach` gerando JavaScript, blocos `@php` no meio da view, e strings de
interface hard-coded (`"Online"`, `"Offline"`, `"Voltar"`) que já violam a regra
de i18n do próprio projeto (`AGENTS.md` §10.5).

Sintomas estruturais equivalentes aparecem em:

- `Devices/Index::updatedPlaceId()` faz **redirect de página inteira** a cada
  troca de filtro.
- `Devices/Edit::addFunction()` / `removeFunction()` e
  `Places/ClonePlace::addMemberRow()` / `removeMemberRow()` gastam um round-trip
  de servidor para manipular estado de formulário puramente local.
- `Places/Members::selectUser()` / `clearSelectedUser()` idem, para um
  autocomplete.

## 2. Decisões tomadas

| Questão | Decisão | Razão |
|---|---|---|
| Inertia ou SPA + API | **Inertia 2** | Único cliente é o navegador. Endpoints JSON pontuais (CEP, autocomplete) convivem com Inertia sem exigir uma camada de API mantida à parte |
| Estratégia | **Big bang**, branch única | Decisão do responsável. Mitigado por fases sequenciais commitáveis (§11) |
| Kit de UI | **shadcn/ui** | Componentes copiados para o repo, editáveis. Resolve dialog, combobox, toast e acessibilidade — a dor central |
| Linguagem do front | **TypeScript** | shadcn é TS; props vindas do Laravel ficam tipadas |
| Rotas no front | **Laravel Wayfinder** | Gera funções TS a partir dos controllers, com parâmetros tipados |
| Permissões | **Preservadas 1:1** | Refinamento adiado para projeto posterior (§15) |
| Comportamento | **Paridade funcional 1:1** | Qualquer mudança de comportamento nesta etapa é bug, não feature |

## 3. Escopo

### Dentro

- Migrar as 25 telas Livewire e as 4 telas de autenticação para páginas Inertia + React.
- Criar o kit de componentes reutilizáveis de página, tabela, paginação e filtro (§7).
- Converter a lógica realtime de Alpine para hooks React testáveis (§6).
- Reescrever os 5 arquivos de teste acoplados a Livewire como testes HTTP com `assertInertia`.
- Remover `app/Livewire/`, `resources/views/livewire/` e o Alpine do layout do cliente.

### Fora

- Painel Filament (`/admin`) — permanece Livewire, intocado.
- Refinamento do modelo de permissões admin/host (§15).
- CRUDs ausentes e novas funcionalidades (§15).
- Redesign visual. As telas devem sair visualmente equivalentes às atuais.
- SSR.

### Restrição que não pode ser violada

**`livewire/livewire` NÃO sai do `composer.json`.** O Filament 4 depende dele
(`filament/filament` v4.7.4 no `composer.lock`). O que é removido é o código de
aplicação em `app/Livewire/` e `resources/views/livewire/`, além das diretivas
`@livewireStyles` / `@livewireScripts` no layout do cliente.

## 4. Stack

| Camada | Escolha | Pacote |
|---|---|---|
| Ponte | Inertia 2 | `inertiajs/inertia-laravel`, `@inertiajs/react` |
| View | React 19 + TypeScript | `react`, `react-dom`, `typescript`, `@vitejs/plugin-react` |
| UI | shadcn/ui sobre Tailwind 4 | Radix UI, `clsx`, `tailwind-merge`, `lucide-react` |
| Rotas tipadas | Laravel Wayfinder | `laravel/wayfinder`, `@laravel/vite-plugin-wayfinder` |
| Formulários | `useForm` do Inertia | — |
| Realtime | Echo + Reverb (já instalados) | `laravel-echo`, `pusher-js` |

Decisões deliberadamente **não** tomadas:

- **Sem SSR.** O app está atrás de login e não tem necessidade de SEO. SSR
  adicionaria mais um processo Node ao supervisord de produção sem ganho.
- **Sem zod / react-hook-form.** A validação continua exclusivamente no Laravel,
  via FormRequest; os erros voltam pela prop `errors` do Inertia. Duplicar regra
  de validação em dois lugares é dívida garantida.
- **Sem state manager global** (Redux, Zustand). Props do Inertia + estado local
  cobrem todos os casos deste app.

## 5. Camada PHP

Cada componente Livewire vira **controller fino** em `app/Http/Controllers/App/`,
seguindo os padrões já estabelecidos no `AGENTS.md` §6:

- Controller: `authorize()` → FormRequest → chama Service → `Inertia::render()`.
- **Regra de negócio permanece nos Services.** `AccessCodeGeneratorService`,
  `DeviceCommandService`, `PlaceCloneService`, `ICalSyncService`,
  `TuyaIntegrationService` não são modificados.
- **Policies preservadas 1:1.** Nenhuma regra de autorização muda.
- **API Resources** em `app/Http/Resources/` serializam as props. São o contrato
  explícito entre PHP e TypeScript e a origem dos tipos em `resources/js/types/`.
- `HandleInertiaRequests` compartilha: usuário autenticado, sessão de
  impersonação, flash `status` e o dicionário de traduções.

**Colisão de nomes:** já existe `App\Http\Controllers\DeviceController` (API de
firmware, consumida pelo hardware via `routes/api.php`). Os novos controllers
ficam sob o namespace `App\Http\Controllers\App\` para não colidir, e a API de
firmware não é tocada.

### 5.1 As três categorias de método Livewire

O mapeamento dos métodos públicos dos 25 componentes revelou três destinos
distintos. Esta classificação guia toda a migração:

**Categoria A — estado de UI puro. Deixa de existir no servidor.**

Vira estado React local, sem rota e sem round-trip:

- `Devices/Edit::addFunction()`, `removeFunction()`
- `Places/ClonePlace::addMemberRow()`, `removeMemberRow()`
- `Places/Members::selectUser()`, `clearSelectedUser()`
- Todos os `updatedPlaceId()`, `updatedSearch()`, `updatedStatus()`,
  `updatedDateFrom()`, `updatedDateTo()`, `updatedSource()`, `updatedGuest()`
  dos componentes de índice

**Categoria B — mutações. Viram rotas POST/PUT/DELETE.**

Com FormRequest e `authorize()` via Policy:

- `save()` de Places, Devices, Bookings, AccessCodes, Integrations
- `Places/Show::removeDevice()`, `Places/Members::addMember()` / `removeMember()`
- `Places/AttachDevice::attach()`, `Places/ClonePlace::clonePlace()`
- `Bookings/Show::deleteBooking()`
- `Integrations/Index::deleteIntegration()`, `Integrations/Edit::updateExternalId()` /
  `removePlace()` / `deleteIntegration()`
- `Places/Control::sendCommand()`, `Devices/Control::sendCommand()`

**Categoria C — endpoints JSON. Consumidos com `fetch` do componente.**

São os "endpoints pontuais" previstos na decisão de arquitetura:

- `Integrations/TuyaConnect::pollQr()` — hoje é `wire:poll`; vira endpoint JSON
  consultado por intervalo no cliente
- Busca de usuários de `Places/Members::getUsersNotInPlaceProperty()` — vira
  endpoint de busca alimentando um combobox

### 5.2 Rotas

As rotas atuais em `routes/web.php` são todas `GET`, porque as ações eram
tratadas pelo próprio Livewire. Cada item da Categoria B passa a exigir uma rota
com verbo próprio. Os nomes `app.*` existentes são preservados sempre que
possível, para não quebrar `route()` em código PHP remanescente.

## 6. Realtime

A máquina de estados Alpine de `places/control.blade.php` e
`devices/control.blade.php` é decomposta em duas peças isoladas e testáveis:

- **`useEcho(channel, event, handler)`** — assina o canal privado e cancela a
  assinatura no unmount. Elimina o vazamento de listeners na navegação.
- **`useDeviceCommands(placeId)`** — reducer com os estados
  `idle → sending → sent → acked`, preservando exatamente as constantes atuais
  (`MIN_BLOCK_MS = 3000`, `ACK_TIMEOUT_MS = 15000`, `ACKED_DISPLAY_MS = 2000`) e
  o casamento por `commandId` ou pelo par `(deviceId, pin)`. Lógica pura,
  testável sem navegador.

Não muda nada no backend: os três canais
(`Place.Device.Status.{placeId}`, `Place.Device.Command.Ack.{placeId}`,
`Place.Device.Function.Status.{placeId}`), `routes/channels.php` e os eventos de
broadcast permanecem idênticos.

## 7. Kit de componentes reutilizáveis

Construído uma única vez na Fase 1 e consumido por todas as telas.

| Componente | Responsabilidade |
|---|---|
| `<Page>` / `<PageHeader>` | Título, ação de voltar, ações do topo |
| `<DataTable>` | Colunas declarativas, estado vazio, ordenação |
| `<Pagination>` | Consome o paginator do Laravel (`->paginate()->withQueryString()`), navega com `preserveState` e `preserveScroll` |
| `<FilterBar>` | Busca com debounce e selects, sincronizados com a query string via visita parcial |
| `<FormField>` | Label, input e erro, ligado ao `errors` do `useForm` |
| `<ConfirmDialog>` | Confirmação de exclusão |
| `<EmptyState>`, `<StatusBadge>`, `<PlaceSelect>`, `<SearchInput>` | Portes diretos dos Blade equivalentes em `resources/views/components/` |
| `<DeviceControlRow>` | Porte de `components/device-control/function-row.blade.php`, ligado ao `useDeviceCommands` |

`<FilterBar>` substitui o redirect de página inteira do `Devices/Index` por uma
visita parcial do Inertia, preservando scroll e foco. É ganho de UX que decorre
da mudança de stack, sem alterar comportamento de negócio.

## 8. i18n

As strings continuam em `resources/lang/pt_BR/app.php` — a regra rígida
`AGENTS.md` §10.5 permanece válida. `HandleInertiaRequests` compartilha o
dicionário como prop e o front consome via hook `useTranslations()`.

Ganho colateral: as strings hard-coded hoje presentes nos Blade (`"Online"`,
`"Offline"`, `"Locais"`, `"Sair"`, `"Voltar"`) são movidas para o arquivo de
tradução durante a migração da tela correspondente.

## 9. Estrutura de diretórios

```
resources/js/
  app.tsx                       createInertiaApp + resolvedor de páginas
  actions/ routes/ wayfinder/   gerado pelo Wayfinder — gitignored
  types/                        Place, Device, Booking, AccessCode, Paginated<T>
  lib/                          cn(), echo.ts, i18n.ts
  hooks/                        useEcho, useDeviceCommands, useTranslations
  layouts/                      app-layout.tsx, guest-layout.tsx
  components/
    ui/                         shadcn (button, dialog, table, select, toast, ...)
    device-control/
    <kit da seção 7>
  pages/
    dashboard.tsx
    auth/ places/ devices/ bookings/ access-codes/ integrations/
```

## 10. Mapeamento das telas

29 páginas React no total: 25 vindas de componentes Livewire e 4 de autenticação.

### Autenticação (4)

Os controllers já existem. Apenas trocam `view()` por `Inertia::render()`.

| Origem | Página |
|---|---|
| `AuthenticatedSessionController` | `auth/login.tsx` |
| `RegisteredUserController` | `auth/register.tsx` |
| `PasswordResetLinkController` | `auth/forgot-password.tsx` |
| `NewPasswordController` | `auth/reset-password.tsx` |

### Dashboard (1)

| Componente Livewire | Controller | Página |
|---|---|---|
| `Dashboard` | `App\DashboardController@index` | `dashboard.tsx` |

### Places (8)

| Componente Livewire | Controller | Página |
|---|---|---|
| `Places/Index` | `PlaceController@index` | `places/index.tsx` |
| `Places/Create` | `PlaceController@create` / `@store` | `places/create.tsx` |
| `Places/Edit` | `PlaceController@edit` / `@update` | `places/edit.tsx` |
| `Places/Show` | `PlaceController@show` + `PlaceDeviceController@destroy` | `places/show.tsx` |
| `Places/Control` | `PlaceControlController@show` + `DeviceCommandController@store` | `places/control.tsx` |
| `Places/Members` | `PlaceMemberController@index` / `@store` / `@destroy` + endpoint de busca (Cat. C) | `places/members.tsx` |
| `Places/ClonePlace` | `PlaceCloneController@create` / `@store` | `places/clone.tsx` |
| `Places/AttachDevice` | `PlaceAttachDeviceController@create` / `@store` | `places/attach-device.tsx` |

### Devices (7)

| Componente Livewire | Controller | Página |
|---|---|---|
| `Devices/Index` | `App\DeviceController@index` | `devices/index.tsx` |
| `Devices/Create` | `App\DeviceController@create` / `@store` | `devices/create.tsx` |
| `Devices/Edit` | `App\DeviceController@edit` / `@update` | `devices/edit.tsx` |
| `Devices/Show` | `App\DeviceController@show` | `devices/show.tsx` |
| `Devices/Control` | `DeviceControlController@show` + `DeviceCommandController@store` | `devices/control.tsx` |
| `Devices/Integrations/Index` | `DeviceIntegrationController@index` | `devices/integrations/index.tsx` |
| `Integrations/TuyaConnect` | `TuyaConnectController@create` / `@store` + `TuyaQrController` (Cat. C) | `devices/integrations/tuya-connect.tsx` |

### Bookings (3)

| Componente Livewire | Controller | Página |
|---|---|---|
| `Bookings/Index` | `BookingController@index` | `bookings/index.tsx` |
| `Bookings/Create` | `BookingController@create` / `@store` | `bookings/create.tsx` |
| `Bookings/Show` | `BookingController@show` / `@destroy` | `bookings/show.tsx` |

### Integrações iCal (3)

| Componente Livewire | Controller | Página |
|---|---|---|
| `Integrations/Index` | `IntegrationController@index` / `@destroy` | `integrations/index.tsx` |
| `Integrations/Create` | `IntegrationController@create` / `@store` | `integrations/create.tsx` |
| `Integrations/Edit` | `IntegrationController@edit` / `@update` + `IntegrationPlaceController@update` / `@destroy` | `integrations/edit.tsx` |

### Access Codes (3)

| Componente Livewire | Controller | Página |
|---|---|---|
| `AccessCodes/Index` | `AccessCodeController@index` | `access-codes/index.tsx` |
| `AccessCodes/Create` | `AccessCodeController@create` / `@store` | `access-codes/create.tsx` |
| `AccessCodes/Edit` | `AccessCodeController@edit` / `@update` | `access-codes/edit.tsx` |

## 11. Fases

Branch única, commits sequenciais. Cada fase é um commit revisável e, da Fase 2
em diante, deixa a aplicação funcionando.

| # | Fase | Conteúdo | Critério de saída |
|---|---|---|---|
| 0 | Fundação | Inertia, React, TS, Wayfinder, shadcn, `vite.config.ts`, `tsconfig.json`, `HandleInertiaRequests`, blade raiz. Zero telas migradas | Suíte verde; app ainda 100% Livewire e funcional |
| 1 | Kit de UI | Layouts, kit da §7, hooks da §6. Nenhuma rota ligada ainda | Suíte verde; testes unitários de `useDeviceCommands` passando |
| 2 | Autenticação (4 telas) | Menor superfície, controllers já existem | Login, registro e recuperação de senha funcionando |
| 3 | Places (8 telas) | Inclui `Control` com realtime — o mais arriscado, deliberadamente cedo | Suíte verde; controle de dispositivo validado manualmente |
| 4 | Devices (7 telas) | Inclui `Control`, Tuya QR e o filtro complexo de `Devices/Index` | Suíte verde; `DevicesIndexScopingTest` e `DevicesIndexFilterTest` reescritos e passando |
| 5 | Bookings + Integrações iCal (6 telas) | | Suíte verde; `IntegrationIcalValidationTest` reescrito |
| 6 | Access Codes (3 telas) | | Suíte verde |
| 7 | Limpeza | Remove `app/Livewire/`, `resources/views/livewire/`, blades órfãos, `@livewireStyles`/`@livewireScripts` e Alpine do layout do cliente | Suíte verde; `/admin` funcionando; `livewire/livewire` intacto no composer |

## 12. Testes

Dos 22 arquivos de teste, **apenas 5 tocam Livewire**:

- `tests/Feature/DevicesIndexFilterTest.php`
- `tests/Feature/DevicesIndexScopingTest.php`
- `tests/Feature/DevicesLivewireTest.php`
- `tests/Feature/IntegrationIcalValidationTest.php`
- `tests/Feature/TuyaConnectImportTest.php`

Esses são reescritos como testes HTTP com `assertInertia`
(`->component('places/index')`, `->has('places', 3)`), cada um na fase do seu
domínio.

Os **17 restantes** — services, parser iCal, schedule, policies e
`PlaceUsersIsolationTest` — não são modificados e devem passar em **todas** as
fases. São o gate de cada commit e a principal justificativa para o big bang ser
defensável neste repositório.

`Tests\TestCase` já aplica `withoutVite()`, então não requer ajuste.

Conforme `AGENTS.md` §7 e §10.7: nenhuma fase é declarada pronta sem
`./vendor/bin/sail test` executado e com a saída verificada.

## 13. Build e deploy

O pipeline atual roda `npm run build` **duas vezes**: no build da imagem
(`docker/prod/Dockerfile`) e novamente no `docker-entrypoint.sh`, para embutir os
`VITE_*` do `.env` montado. Em ambos os momentos PHP e `artisan` estão
disponíveis no mesmo container, e `composer install --no-dev` já rodou antes.

Portanto o plugin Vite do Wayfinder — que dispara `artisan wayfinder:generate`
durante o build — funciona no pipeline atual **sem alteração no Dockerfile nem
no entrypoint**.

Os artefatos gerados (`resources/js/actions`, `resources/js/routes`,
`resources/js/wayfinder`) entram no `.gitignore`.

**Contingência:** se `wayfinder:generate` falhar no build da imagem por ausência
de `.env`, os artefatos gerados passam a ser commitados no repositório em vez de
ignorados. Isso remove a dependência de `artisan` no momento do build. A Fase 0
deve validar esse ponto explicitamente rodando o build de produção.

## 14. Riscos

| Risco | Mitigação |
|---|---|
| Branch longa acumulando conflito | Fases commitáveis; nenhum outro trabalho de front em paralelo durante a migração |
| Regressão silenciosa de comportamento | Paridade 1:1 declarada; os 17 testes não-Livewire como gate de cada fase |
| Remoção acidental de `livewire/livewire` quebrando o Filament | Registrado como restrição dura (§3); critério de saída da Fase 7 inclui verificar `/admin` |
| Máquina de estados do realtime portada incorretamente | Extraída como reducer puro com testes unitários próprios, antes de ser ligada à tela (Fase 1) |
| Escopo por place divergir do atual ao portar para controller | Query portada literalmente, sem refatoração, na mesma fase |
| Wayfinder falhar no build de produção | Contingência documentada em §13; validada na Fase 0 |

## 15. Dívidas conhecidas, deliberadamente fora deste projeto

Levantadas durante o design e **conscientemente adiadas** para não transformar
uma migração de stack em caça a regressão. Devem virar um projeto próprio depois
que o React estiver de pé:

1. **Escopo por place duplicado.** `Places/Index` filtra com
   `whereHas('placeUsers', ...)`; `Devices/Index` reimplementa uma variante de
   ~40 linhas com `device_user`. Não existe scope central. Cada tela nova é uma
   chance nova de vazamento entre contas.
2. **`PlacePolicy` permissiva.** `viewAny()` e `create()` retornam `true` fixo.
   `update()` e `delete()` checam apenas vínculo, não papel — um `host` pode
   apagar o place. Só `replicate()` e `manageMembers()` exigem `admin`.
3. **`PlaceRoleEnum` subaproveitado.** Os papéis `admin` e `host` existem no
   banco e quase não são aplicados na autorização.
4. **`Schema::hasTable('device_user')` em tempo de request**, dentro do
   `render()` de `Devices/Index`.
5. **CRUDs ausentes**, a serem levantados no projeto seguinte.

Estes itens são **portados como estão**. Corrigi-los durante o big bang
impossibilitaria distinguir bug de migração de mudança intencional.
