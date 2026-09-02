# Reorganização da navegação do app do cliente — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reorganizar a navegação de `/app/*` conforme
[docs/specs/2026-09-01-navegacao-app-cliente.md](../specs/2026-09-01-navegacao-app-cliente.md):
sidebar em grupos, local atual persistido, Controle promovido, breadcrumb real e integrações
simétricas.

**Architecture:** Seis ondas sequenciais. Dentro de cada onda, agentes rodam em paralelo com
**conjuntos de arquivos disjuntos**. As ondas de fundação (0a e 0b) criam tudo que é
compartilhado — ambiente de teste de componente, factories, todas as chaves de i18n, os
componentes novos, o scope de disponibilidade — para que nenhuma onda posterior precise
disputar os mesmos arquivos. Entre ondas há uma barreira: suíte completa verde antes de
começar a próxima.

**Tech Stack:** Laravel 11 · Inertia 2 + React 19 + TypeScript · Tailwind 4 · PHPUnit 11 ·
Vitest · tudo via `./vendor/bin/sail`.

---

## 0. Regras de execução paralela

Estas regras não são estilo. Violá-las corrompe a árvore de trabalho de outro agente.

### 0.1 Árvore de trabalho compartilhada

**Não use git worktrees neste repositório.** Worktrees do PortaTec nascem sem `vendor/`,
`node_modules/` e ambiente Sail — um agente ali não consegue rodar teste nenhum. Todos os
agentes trabalham no **clone principal**, na mesma árvore, ao mesmo tempo.

Consequências, todas obrigatórias:

1. **Cada arquivo tem exatamente um dono por onda.** O mapa da seção 0.3 é o contrato. Um
   agente que precise editar arquivo que não é seu **para** e reporta, em vez de editar.
2. **Nunca `git add -A`, `git add .` ou `git commit -a`.** Sempre
   `git add <caminhos exatos do seu escopo>`. Um `add -A` engole o trabalho pela metade de
   outro agente e produz um commit quebrado.
3. **Nunca `git checkout`, `git stash`, `git reset` ou troca de branch.** Todos compartilham
   o mesmo `HEAD`.
4. **Ao rodar a suíte, espere falhas alheias.** Outro agente pode estar no meio de uma
   edição. O que importa é: **os testes do seu escopo passam**. Rode-os por nome
   (`--filter=`), não a suíte inteira. A suíte inteira é responsabilidade da barreira de fim
   de onda.

### 0.2 Ondas e barreiras

| Onda | Conteúdo | Agentes em paralelo | Depende de |
|---|---|---|---|
| **0a** | Pré-requisitos: ambiente de teste React, factories | 2 | — |
| **0b** | Fundação: i18n, componentes novos, scope de disponibilidade | 5 | 0a |
| **1** | Páginas e controllers dos ganhos imediatos (F1) | 5 | 0b |
| **2** | Layout: sidebar em grupos, menu de usuário, breadcrumb (F2, F3) | 1 | 0b |
| **3** | Adoção do breadcrumb nas telas | 4 | 2 |
| **4** | Local atual (F4) | 1, depois 5 | 2 |
| **5** | Tela de Controle (F5) | 1 | 4 |

Ondas 1 e 2 são independentes entre si e **podem rodar juntas** — os conjuntos de arquivos
não se cruzam (confira no mapa 0.3). Se rodarem juntas, são 6 agentes simultâneos.

**Barreira de fim de onda** — o coordenador roda, e só avança com tudo verde:

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test && ./vendor/bin/sail npm run test:js
```

### 0.3 Mapa de propriedade de arquivos

Cada linha é a propriedade **exclusiva** de uma tarefa dentro da sua onda.

| Arquivo | Onda 0 | Onda 1 | Onda 2 | Onda 3 | Onda 4 | Onda 5 |
|---|---|---|---|---|---|---|
| `package.json` / `package-lock.json` / `vite.config.ts` | **T0.0** (0a) | — | — | — | — | — |
| `database/factories/*` | **T0.0b** (0a) | — | — | — | — | — |
| `app/Models/Place.php`, `app/Models/Booking.php` | **T0.0b** (0a) | — | — | — | — | — |
| `resources/lang/pt_BR/app.php` | **T0.1** | — | — | — | — | — |
| `resources/js/components/stat-tile.tsx` | **T0.2** | — | — | — | — | — |
| `resources/js/components/breadcrumbs.tsx` | **T0.3** | — | — | — | — | — |
| `resources/js/components/user-menu.tsx` | **T0.4** | — | — | — | — | — |
| `app/Models/Device.php` | **T0.0b** (0a), depois **T0.5** (0b) | — | — | — | — | — |
| `resources/js/pages/places/show.tsx` | — | **T1.1** | — | **T3.1** | — | — |
| `app/Http/Controllers/App/PlaceController.php` | — | **T1.1** | — | — | — | — |
| `resources/js/pages/dashboard.tsx` | — | **T1.2** | — | — | — | — |
| `app/Http/Controllers/App/DeviceController.php` | — | **T1.3** | — | — | **T4.4** | — |
| `resources/js/pages/devices/index.tsx` | — | **T1.3** | — | — | **T4.4** | — |
| `resources/js/pages/access-codes/index.tsx` | — | **T1.4** | — | **T3.4** | **T4.3** | — |
| `resources/js/pages/bookings/show.tsx` | — | **T1.4** | — | **T3.3** | — | — |
| `resources/js/pages/bookings/index.tsx` | — | **T1.4** | — | — | **T4.2** | — |
| `app/Http/Controllers/App/IntegrationController.php` | — | **T1.5** | — | — | **T4.5** | — |
| `resources/js/pages/integrations/create.tsx` | — | **T1.5** | — | — | — | — |
| `resources/js/layouts/app-layout.tsx` | — | — | **T2.1/T2.2** | — | **T4.6** | **T5.2** |
| `resources/js/components/__tests__/nav-link.test.ts` | — | — | **T2.1** | — | — | — |
| páginas de `places/*` (menos `show`) | — | — | — | **T3.1** | — | — |
| páginas de `devices/*` (menos `index`) | — | — | — | **T3.2** | — | — |
| `app/Services/CurrentPlaceService.php` | — | — | — | — | **T4.1** | — |
| `app/Http/Middleware/HandleInertiaRequests.php` | — | — | — | — | **T4.1** | — |
| `routes/web.php` | — | — | — | — | **T4.1** | **T5.1** |
| `app/Http/Controllers/App/AccessCodeController.php` | — | — | — | — | **T4.3** | — |
| `app/Http/Controllers/App/ControlController.php` | — | — | — | — | — | **T5.1** |
| `resources/js/pages/control/index.tsx` | — | — | — | — | — | **T5.1** |

`app-layout.tsx` aparece em quatro ondas e **nunca** em duas tarefas da mesma onda. Essa é a
razão de as fases de layout serem de agente único.

### 0.4 Convenções do repositório que todo agente deve seguir

- Todo comando PHP/Composer/Artisan/NPM roda via `./vendor/bin/sail`. Nunca no host.
- `declare(strict_types=1);` no topo de arquivo novo em `app/`.
- Regra de negócio em Service, não em controller. Autorização via Policy. Nada de SQL cru.
- **Nenhuma string de UI fora de `resources/lang/pt_BR/app.php`.** Como T0.1 já cadastrou
  todas as chaves, seu trabalho é só consumir via `t('chave')`.
- Commits em Conventional Commits (`feat:`, `fix:`, `refactor:`, `test:`).
- **Não faça push e não abra PR.** O coordenador cuida disso ao fim de cada onda.

---

## ONDA 0a — Pré-requisitos

**Dois agentes em paralelo.** T0.0 é só JS, T0.0b é só PHP — conjuntos disjuntos. As duas
existem porque criam infraestrutura de teste que **várias** tarefas posteriores consomem; se
cada tarefa criasse a sua, agentes paralelos escreveriam os mesmos arquivos.

---

### T0.0b: Factories de teste

**Por que é pré-requisito:** o repositório só tem `UserFactory`. Os testes de T0.5, T1.1,
T1.3 e T1.4 precisam criar dispositivos e reservas em quantidade, e quatro agentes paralelos
criando as mesmas factories colidiriam. Esta tarefa as cria de uma vez.

**Files:**
- Create: `database/factories/PlaceFactory.php`
- Create: `database/factories/DeviceFactory.php`
- Create: `database/factories/BookingFactory.php`
- Create: `tests/Unit/FactoriesTest.php`

- [ ] **Step 1: Escreva o teste que falha**

Criar `tests/Unit/FactoriesTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DeviceBrandEnum;
use App\Models\Booking;
use App\Models\Device;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_place_factory_creates_a_place(): void
    {
        $this->assertNotNull(Place::factory()->create()->name);
    }

    public function test_device_factory_defaults_to_portatec_and_accepts_overrides(): void
    {
        $default = Device::factory()->create();
        $this->assertSame(DeviceBrandEnum::Portatec, $default->brand);

        $tuya = Device::factory()->create(['brand' => DeviceBrandEnum::Tuya, 'tuya_online' => true]);
        $this->assertTrue($tuya->isAvailable());
    }

    public function test_booking_factory_creates_a_place_and_a_coherent_window(): void
    {
        $booking = Booking::factory()->create();

        $this->assertNotNull($booking->place_id);
        $this->assertTrue($booking->check_out->greaterThan($booking->check_in));
    }
}
```

- [ ] **Step 2: Rode o teste e veja falhar**

```bash
./vendor/bin/sail test --filter=FactoriesTest
```

Esperado: FAIL — `Call to undefined method App\Models\Place::factory()`.

- [ ] **Step 3: Escreva as factories**

Criar `database/factories/PlaceFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Place>
 */
class PlaceFactory extends Factory
{
    protected $model = Place::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->streetName(),
        ];
    }
}
```

Criar `database/factories/DeviceFactory.php`. O padrão é portatec com `last_sync` recente,
ou seja **online** — os testes que querem offline sobrescrevem `last_sync`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'place_id' => null,
            'name' => $this->faker->words(2, true),
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now()->subMinute(),
            'tuya_online' => null,
        ];
    }
}
```

Criar `database/factories/BookingFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = now()->addDays($this->faker->numberBetween(1, 30));

        return [
            'place_id' => Place::factory(),
            'guest_name' => $this->faker->name(),
            'check_in' => $checkIn,
            'check_out' => (clone $checkIn)->addDays($this->faker->numberBetween(1, 5)),
            'source' => 'manual',
        ];
    }
}
```

- [ ] **Step 4: Habilite `factory()` nos models**

`Place`, `Device` e `Booking` precisam do trait. Em cada um dos três models, acrescente:

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;
```

e o `use HasFactory;` junto dos traits já existentes da classe (`Device` e `Booking` já usam
`SoftDeletes`; some o `HasFactory` à mesma linha ou logo abaixo).

- [ ] **Step 5: Rode o teste e veja passar**

```bash
./vendor/bin/sail test --filter=FactoriesTest
```

Esperado: PASS (3 testes).

- [ ] **Step 6: Confirme que nada regrediu**

```bash
./vendor/bin/sail test
```

Esperado: PASS. Acrescentar `HasFactory` não muda comportamento de produção, mas a suíte
inteira é barata aqui e esta tarefa toca três models.

- [ ] **Step 7: Commit**

```bash
git add database/factories/ app/Models/Place.php app/Models/Device.php app/Models/Booking.php tests/Unit/FactoriesTest.php
git commit -m "test: adiciona factories de Place, Device e Booking"
```

**Atenção:** esta tarefa toca `app/Models/Device.php`, que na onda 0b pertence à T0.5. Por
isso ela está numa onda anterior — as duas **nunca** rodam ao mesmo tempo.

---

### T0.0: Ambiente de teste de componente React

**Por que é uma onda própria:** os testes JS que existem hoje
(`nav-link.test.ts`, `filter-bar-params.test.ts`, `device-commands-reducer.test.ts`) são
todos de **função pura** — nenhum renderiza componente. O projeto não tem
`@testing-library/react` nem `jsdom`, e `vite.config.ts` não tem bloco `test`. As tarefas
T0.2, T0.3 e T0.4 renderizam componentes; sem isto, as três falham. E se cada uma
instalasse por conta própria, três agentes escreveriam `package.json`, `package-lock.json` e
`vite.config.ts` ao mesmo tempo — exatamente a corrupção que a regra 0.1 proíbe.

Por isso esta tarefa roda **sozinha**, antes de qualquer outra do plano.

**Files:**
- Modify: `package.json`
- Modify: `vite.config.ts`
- Create: `resources/js/test-setup.ts`
- Create: `resources/js/components/__tests__/test-environment.test.tsx`

- [ ] **Step 1: Instale as dependências**

```bash
./vendor/bin/sail npm install -D @testing-library/react @testing-library/dom @testing-library/jest-dom jsdom
```

- [ ] **Step 2: Crie o arquivo de setup**

Criar `resources/js/test-setup.ts`:

```ts
import '@testing-library/jest-dom/vitest';
```

- [ ] **Step 3: Configure o Vitest**

Em `vite.config.ts`, acrescente o bloco `test` ao objeto de configuração exportado
(irmão de `plugins`, `resolve` etc.):

```ts
test: {
    environment: 'jsdom',
    setupFiles: ['./resources/js/test-setup.ts'],
    globals: false,
},
```

`globals: false` mantém os imports explícitos de `vitest` que os testes atuais já usam.

- [ ] **Step 4: Escreva o teste que prova o ambiente**

Criar `resources/js/components/__tests__/test-environment.test.tsx`:

```tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

/**
 * Guarda do ambiente: se alguém remover o jsdom ou o setup do Vitest, esta é a
 * primeira coisa a quebrar, com mensagem clara — em vez de cada teste de
 * componente falhar com "document is not defined".
 */
describe('ambiente de teste de componente', () => {
    it('renderiza JSX num DOM', () => {
        render(<p>ok</p>);

        expect(screen.getByText('ok')).toBeInTheDocument();
    });
});
```

- [ ] **Step 5: Rode a suíte JS inteira**

```bash
./vendor/bin/sail npm run test:js
```

Esperado: PASS — o teste novo **e** os três de função pura que já existiam. Se algum dos
antigos quebrar, a causa é o `globals`/`environment`: eles não esperam DOM, mas também não
são incompatíveis com ele.

- [ ] **Step 6: Commit**

```bash
git add package.json package-lock.json vite.config.ts resources/js/test-setup.ts resources/js/components/__tests__/test-environment.test.tsx
git commit -m "chore: habilita teste de componente React com jsdom e testing-library"
```

---

### ⛔ BARREIRA — fim da onda 0a

```bash
./vendor/bin/sail test && ./vendor/bin/sail npm run test:js
```

Só despache a onda 0b com esta verde. T0.2/T0.3/T0.4 dependem do ambiente React de T0.0;
T0.5, T1.1, T1.3 e T1.4 dependem das factories de T0.0b.

---

## ONDA 0b — Fundação

Cinco agentes em paralelo. Nenhum depende do outro.

---

### T0.1: Todas as chaves de i18n

Esta tarefa é dona de `resources/lang/pt_BR/app.php` **para o plano inteiro**. Ela cadastra
de uma vez as chaves de todas as cinco ondas, para que nenhuma tarefa posterior precise tocar
neste arquivo. Nada mais neste plano edita esse arquivo.

**Files:**
- Modify: `resources/lang/pt_BR/app.php`
- Test: `tests/Unit/TranslationKeysTest.php` (criar)

- [ ] **Step 1: Escreva o teste que falha**

Criar `tests/Unit/TranslationKeysTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

/**
 * As chaves de navegação são cadastradas de uma vez pela tarefa de fundação do
 * plano de reorganização da navegação, para que as tarefas seguintes apenas as
 * consumam. Este teste fixa esse contrato.
 */
class TranslationKeysTest extends TestCase
{
    public function test_navigation_keys_exist(): void
    {
        $keys = [
            'nav_group_operation',
            'nav_group_setup',
            'nav_control',
            'control_all_places',
            'control_index_title',
            'breadcrumb_home',
            'place_select_all',
            'place_select_label',
            'devices_status_label',
            'devices_status_online',
            'devices_status_offline',
            'devices_only_unassigned',
            'place_booking_sources_heading',
            'place_add_booking_source',
            'place_no_booking_sources',
            'dashboard_active_codes_heading',
            'user_menu_label',
        ];

        foreach ($keys as $key) {
            $this->assertNotSame(
                "app.{$key}",
                trans("app.{$key}"),
                "A chave de tradução [app.{$key}] não existe."
            );
        }
    }

    public function test_dashboard_nav_label_is_translated_to_portuguese(): void
    {
        $this->assertSame('Início', trans('app.nav_dashboard'));
    }

    public function test_removed_key_is_gone(): void
    {
        $this->assertSame(
            'app.nav_bookings_integrations',
            trans('app.nav_bookings_integrations'),
            'A chave [app.nav_bookings_integrations] deveria ter sido removida: '
            .'os dois cabeçalhos de integrações passam a usar [app.integrations].'
        );
    }
}
```

- [ ] **Step 2: Rode o teste e veja falhar**

```bash
./vendor/bin/sail test --filter=TranslationKeysTest
```

Esperado: FAIL — as chaves novas não existem e `nav_dashboard` ainda é `Dashboard`.

- [ ] **Step 3: Aplique as mudanças no arquivo de tradução**

Em `resources/lang/pt_BR/app.php`:

**3a — altere** o valor de `nav_dashboard`:

```php
'nav_dashboard' => 'Início',
```

**3b — remova** a linha da chave `nav_bookings_integrations` (hoje `'Integrações iCal'`).
Os dois cabeçalhos de integrações passam a usar a chave `integrations`, que já existe.

**3c — altere** `integrations_ical_title`:

```php
'integrations_ical_title' => 'Integrações de reservas',
```

**3d — acrescente**, junto ao bloco de chaves `nav_*`:

```php
'nav_group_operation' => 'Operação',
'nav_group_setup' => 'Configuração',
'nav_control' => 'Controle',
'user_menu_label' => 'Conta',
'breadcrumb_home' => 'Início',
'place_select_label' => 'Local atual',
'place_select_all' => 'Todos os locais',
'control_index_title' => 'Controle',
'control_all_places' => 'Ver todos os locais',
'devices_status_label' => 'Status',
'devices_status_online' => 'Online',
'devices_status_offline' => 'Offline',
'devices_only_unassigned' => 'Somente sem local',
'place_booking_sources_heading' => 'Fontes de reserva',
'place_add_booking_source' => 'Adicionar fonte',
'place_no_booking_sources' => 'Nenhuma fonte de reserva conectada a este local.',
'dashboard_active_codes_heading' => 'Códigos ativos',
```

- [ ] **Step 4: Rode o teste e veja passar**

```bash
./vendor/bin/sail test --filter=TranslationKeysTest
```

Esperado: PASS (3 testes).

- [ ] **Step 5: Confirme que nada quebrou pela remoção da chave**

```bash
grep -rn "nav_bookings_integrations" resources/ app/
```

Esperado: dois resultados — `app-layout.tsx` e `bookings/index.tsx`. **Não corrija agora**:
esses arquivos pertencem a T2.1 e T1.4, que trocam a chave por `integrations`. `t()` com
chave ausente devolve a própria chave, então a tela mostra texto feio, mas não quebra. A
janela fecha no fim da onda 1.

- [ ] **Step 6: Commit**

```bash
git add resources/lang/pt_BR/app.php tests/Unit/TranslationKeysTest.php
git commit -m "feat: cadastra chaves de i18n da reorganizacao de navegacao"
```

---

### T0.2: Componente `StatTile`

Hoje o mesmo bloco de tile está copiado cinco vezes entre `dashboard.tsx` (4×) e
`places/show.tsx` (3×). Esta tarefa extrai o componente; as tarefas T1.1 e T1.2 substituem as
cópias.

**Files:**
- Create: `resources/js/components/stat-tile.tsx`
- Create: `resources/js/components/__tests__/stat-tile.test.tsx`

- [ ] **Step 1: Escreva o teste que falha**

Criar `resources/js/components/__tests__/stat-tile.test.tsx`:

```tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { StatTile } from '@/components/stat-tile';

/**
 * O tile é o mesmo bloco visual do dashboard e do detalhe do local. A única
 * diferença de comportamento é ser ou não clicável: sem `href` ele é um bloco
 * inerte; com `href`, um link para a lista já filtrada.
 */
describe('StatTile', () => {
    it('renderiza rótulo e valor', () => {
        render(<StatTile label="Dispositivos" value="3" />);

        expect(screen.getByText('Dispositivos')).toBeTruthy();
        expect(screen.getByText('3')).toBeTruthy();
    });

    it('não é um link quando não recebe href', () => {
        render(<StatTile label="Dispositivos" value="3" />);

        expect(screen.queryByRole('link')).toBeNull();
    });

    it('é um link para o href quando recebe href', () => {
        render(<StatTile label="Reservas" value="7" href="/app/bookings?place_id=1" />);

        expect(screen.getByRole('link').getAttribute('href')).toBe('/app/bookings?place_id=1');
    });

    it('usa a faixa de alerta quando tone é error', () => {
        const { container } = render(<StatTile label="Offline" value="2" tone="error" />);

        expect(container.querySelector('.bg-error-500')).toBeTruthy();
    });
});
```

- [ ] **Step 2: Rode o teste e veja falhar**

```bash
./vendor/bin/sail npm run test:js -- stat-tile
```

Esperado: FAIL — `Failed to resolve import "@/components/stat-tile"`.

Se falhar com `document is not defined` ou por falta de `@testing-library/react`, a onda 0a
não rodou. **Pare e reporte** — não instale nada: `package.json` pertence à T0.0, e dois
agentes escrevendo nele ao mesmo tempo corrompem a árvore.

- [ ] **Step 3: Escreva a implementação mínima**

Criar `resources/js/components/stat-tile.tsx`:

```tsx
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

export type StatTileTone = 'default' | 'error';

export interface StatTileProps {
    /** Rótulo em caixa alta, acima do número. */
    label: string;
    /** Valor já formatado (ex.: "3" ou "2/5"). */
    value: ReactNode;
    /** Quando presente, o tile inteiro vira link para a lista já filtrada. */
    href?: string;
    /** `error` pinta a faixa lateral e o número de vermelho. */
    tone?: StatTileTone;
    className?: string;
}

const BASE_CLASS =
    'relative block overflow-hidden rounded-lg border border-neutral-200 bg-white py-3.5 pr-4 pl-[18px] no-underline';

export function StatTile({ label, value, href, tone = 'default', className }: StatTileProps) {
    const content = (
        <>
            <span
                className={cn('absolute inset-y-0 left-0 w-[3px]', tone === 'error' ? 'bg-error-500' : 'bg-primary-500')}
                aria-hidden="true"
            />
            <p className="m-0 text-[11px] font-bold tracking-wide text-neutral-400 uppercase">{label}</p>
            <p
                className={cn(
                    'm-0 mt-2 font-mono text-2xl font-bold tabular-nums',
                    tone === 'error' ? 'text-error-700' : 'text-neutral-900',
                )}
            >
                {value}
            </p>
        </>
    );

    if (href === undefined) {
        return <div className={cn(BASE_CLASS, className)}>{content}</div>;
    }

    return (
        <Link href={href} className={cn(BASE_CLASS, 'hover:border-primary-300', className)}>
            {content}
        </Link>
    );
}
```

- [ ] **Step 4: Rode o teste e veja passar**

```bash
./vendor/bin/sail npm run test:js -- stat-tile
```

Esperado: PASS (4 testes).

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/stat-tile.tsx resources/js/components/__tests__/stat-tile.test.tsx
git commit -m "feat: extrai componente StatTile reutilizavel"
```

---

### T0.3: Componente `Breadcrumbs`

Componente puro. A integração no layout é da T2.2 — **não** edite `app-layout.tsx` aqui.

**Files:**
- Create: `resources/js/components/breadcrumbs.tsx`
- Create: `resources/js/components/__tests__/breadcrumbs.test.tsx`

- [ ] **Step 1: Escreva o teste que falha**

Criar `resources/js/components/__tests__/breadcrumbs.test.tsx`:

```tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { Breadcrumbs } from '@/components/breadcrumbs';

describe('Breadcrumbs', () => {
    const trail = [
        { label: 'Locais', href: '/app/places' },
        { label: 'Casa Azul', href: '/app/places/1' },
        { label: 'Membros' },
    ];

    it('renderiza todos os itens da trilha', () => {
        render(<Breadcrumbs items={trail} />);

        expect(screen.getByText('Locais')).toBeTruthy();
        expect(screen.getByText('Casa Azul')).toBeTruthy();
        expect(screen.getByText('Membros')).toBeTruthy();
    });

    it('transforma em link todos os itens com href, menos o último', () => {
        render(<Breadcrumbs items={trail} />);

        const links = screen.getAllByRole('link');

        expect(links).toHaveLength(2);
        expect(links[0].getAttribute('href')).toBe('/app/places');
        expect(links[1].getAttribute('href')).toBe('/app/places/1');
    });

    it('nunca transforma o último item em link, mesmo com href', () => {
        render(<Breadcrumbs items={[{ label: 'Locais', href: '/app/places' }]} />);

        expect(screen.queryByRole('link')).toBeNull();
    });

    it('marca o último item como a página atual', () => {
        render(<Breadcrumbs items={trail} />);

        expect(screen.getByText('Membros').getAttribute('aria-current')).toBe('page');
    });
});
```

- [ ] **Step 2: Rode o teste e veja falhar**

```bash
./vendor/bin/sail npm run test:js -- breadcrumbs
```

Esperado: FAIL — `Failed to resolve import "@/components/breadcrumbs"`.

- [ ] **Step 3: Escreva a implementação mínima**

Criar `resources/js/components/breadcrumbs.tsx`:

```tsx
import { Link } from '@inertiajs/react';
import { Fragment } from 'react';

import { cn } from '@/lib/utils';

export interface Crumb {
    label: string;
    /** Sem href, o item é texto. O último item nunca vira link, tenha href ou não. */
    href?: string;
}

export interface BreadcrumbsProps {
    items: Crumb[];
    className?: string;
}

export function Breadcrumbs({ items, className }: BreadcrumbsProps) {
    return (
        <nav aria-label="breadcrumb" className={cn('text-[12.5px] text-neutral-400', className)}>
            {items.map((item, index) => {
                const isLast = index === items.length - 1;

                return (
                    <Fragment key={`${item.label}-${index}`}>
                        {index > 0 ? <span className="mx-1" aria-hidden="true">/</span> : null}
                        {isLast || item.href === undefined ? (
                            <span
                                aria-current={isLast ? 'page' : undefined}
                                className={cn(isLast && 'font-semibold text-neutral-700')}
                            >
                                {item.label}
                            </span>
                        ) : (
                            <Link href={item.href} className="text-neutral-400 no-underline hover:text-neutral-700">
                                {item.label}
                            </Link>
                        )}
                    </Fragment>
                );
            })}
        </nav>
    );
}
```

- [ ] **Step 4: Rode o teste e veja passar**

```bash
./vendor/bin/sail npm run test:js -- breadcrumbs
```

Esperado: PASS (4 testes).

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/breadcrumbs.tsx resources/js/components/__tests__/breadcrumbs.test.tsx
git commit -m "feat: adiciona componente Breadcrumbs"
```

---

### T0.4: Componente `UserMenu`

Componente puro, recebe tudo por prop. A integração no layout é da T2.1 — **não** edite
`app-layout.tsx` aqui.

**Files:**
- Create: `resources/js/components/user-menu.tsx`
- Create: `resources/js/components/__tests__/user-menu.test.tsx`

- [ ] **Step 1: Escreva o teste que falha**

Criar `resources/js/components/__tests__/user-menu.test.tsx`:

```tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { UserMenu } from '@/components/user-menu';

/**
 * O item "Admin" some para quem não é super admin — a mesma condição que o
 * `User::canAccessPanel` aplica no backend. Mostrar um link que devolve 403 é
 * pior do que não mostrar link nenhum.
 */
describe('UserMenu', () => {
    it('mostra nome e e-mail do usuário', () => {
        render(<UserMenu name="Rodrigo" email="rodrigo@exemplo.com" isSuperAdmin={false} />);

        expect(screen.getByText('Rodrigo')).toBeTruthy();
        expect(screen.getByText('rodrigo@exemplo.com')).toBeTruthy();
    });

    it('não oferece Admin para usuário comum', () => {
        render(<UserMenu name="Rodrigo" email="rodrigo@exemplo.com" isSuperAdmin={false} />);

        expect(screen.queryByText('Admin')).toBeNull();
    });

    it('oferece Admin para super admin', () => {
        render(<UserMenu name="Rodrigo" email="rodrigo@exemplo.com" isSuperAdmin />);

        expect(screen.getByText('Admin')).toBeTruthy();
    });
});
```

- [ ] **Step 2: Rode o teste e veja falhar**

```bash
./vendor/bin/sail npm run test:js -- user-menu
```

Esperado: FAIL — `Failed to resolve import "@/components/user-menu"`.

- [ ] **Step 3: Escreva a implementação mínima**

Criar `resources/js/components/user-menu.tsx`. O menu fica sempre expandido (sem `dropdown`),
porque no rodapé de uma sidebar escura o popover disputa espaço com a gaveta do mobile e não
paga o custo:

```tsx
import { Link } from '@inertiajs/react';

import { logout } from '@/routes';
import { useTranslations } from '@/hooks/use-translations';

export interface UserMenuProps {
    name: string;
    email: string;
    isSuperAdmin: boolean;
    /** Fecha a gaveta no mobile ao navegar. */
    onNavigate?: () => void;
}

const LINK_CLASS =
    'block rounded-lg px-2.5 py-1.5 text-[13px] font-medium text-neutral-400 no-underline hover:text-neutral-100';

export function UserMenu({ name, email, isSuperAdmin, onNavigate }: UserMenuProps) {
    const { t } = useTranslations();

    return (
        <div className="mt-auto border-t border-white/10 pt-3">
            <div className="px-2.5 pb-2">
                <p className="m-0 truncate text-[13px] font-semibold text-neutral-100">{name}</p>
                <p className="m-0 truncate text-[11.5px] text-neutral-500">{email}</p>
            </div>

            {isSuperAdmin ? (
                <a href="/admin" className={LINK_CLASS}>
                    Admin
                </a>
            ) : null}

            <Link
                href={logout.url()}
                method="post"
                as="button"
                onClick={onNavigate}
                className={`${LINK_CLASS} w-full cursor-pointer border-0 bg-transparent text-left`}
            >
                {t('nav_logout')}
            </Link>
        </div>
    );
}
```

O texto "Admin" fica literal porque é nome próprio do painel, não frase de interface — é como
`app-layout.tsx` já o trata hoje via `t('nav_admin')`; se preferir manter a chave, use
`t('nav_admin')` e ajuste o teste para o valor traduzido.

- [ ] **Step 4: Rode o teste e veja passar**

```bash
./vendor/bin/sail npm run test:js -- user-menu
```

Esperado: PASS (3 testes).

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/user-menu.tsx resources/js/components/__tests__/user-menu.test.tsx
git commit -m "feat: adiciona componente UserMenu"
```

---

### T0.5: Scopes de disponibilidade em `Device`

**Por que existe:** `Device::isAvailable()` é um método PHP —
`last_sync->diffInMinutes(now()) < 10` para portatec, `tuya_online` para Tuya. A lista de
dispositivos é **paginada**, então filtrar por status em PHP depois da paginação devolveria
páginas incompletas. O filtro precisa ser SQL, e a regra passa a existir em dois lugares. O
teste desta tarefa é o que impede os dois de divergirem.

**Files:**
- Modify: `app/Models/Device.php`
- Test: `tests/Unit/DeviceAvailabilityScopeTest.php` (criar)

- [ ] **Step 1: Escreva o teste que falha**

Criar `tests/Unit/DeviceAvailabilityScopeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A regra de disponibilidade passa a existir em dois lugares: o método
 * `isAvailable()` (usado nas telas, item a item) e os scopes (usados na lista
 * paginada, que precisa filtrar em SQL). Este teste existe para que os dois não
 * divirjam — se alguém mudar a janela de 10 minutos num lugar só, ele quebra.
 */
class DeviceAvailabilityScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scopes_agree_with_the_is_available_method(): void
    {
        $onlinePortatec = Device::factory()->create([
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now()->subMinutes(2),
        ]);
        $offlinePortatec = Device::factory()->create([
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now()->subMinutes(30),
        ]);
        $neverSynced = Device::factory()->create([
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => null,
        ]);
        $onlineTuya = Device::factory()->create([
            'brand' => DeviceBrandEnum::Tuya,
            'tuya_online' => true,
        ]);
        $offlineTuya = Device::factory()->create([
            'brand' => DeviceBrandEnum::Tuya,
            'tuya_online' => false,
        ]);

        $availableIds = Device::query()->available()->pluck('id')->sort()->values()->all();
        $unavailableIds = Device::query()->unavailable()->pluck('id')->sort()->values()->all();

        $expectedAvailable = Device::all()
            ->filter(fn (Device $device) => $device->isAvailable())
            ->pluck('id')->sort()->values()->all();
        $expectedUnavailable = Device::all()
            ->reject(fn (Device $device) => $device->isAvailable())
            ->pluck('id')->sort()->values()->all();

        $this->assertSame($expectedAvailable, $availableIds);
        $this->assertSame($expectedUnavailable, $unavailableIds);

        $this->assertContains($onlinePortatec->id, $availableIds);
        $this->assertContains($onlineTuya->id, $availableIds);
        $this->assertContains($offlinePortatec->id, $unavailableIds);
        $this->assertContains($neverSynced->id, $unavailableIds);
        $this->assertContains($offlineTuya->id, $unavailableIds);
    }

    public function test_the_two_scopes_partition_the_whole_table(): void
    {
        Device::factory()->count(6)->create();

        $this->assertSame(
            Device::query()->count(),
            Device::query()->available()->count() + Device::query()->unavailable()->count(),
        );
    }
}
```

- [ ] **Step 2: Rode o teste e veja falhar**

```bash
./vendor/bin/sail test --filter=DeviceAvailabilityScopeTest
```

Esperado: FAIL — `Call to undefined method ...::available()`.

Se falhar antes disso, em `Device::factory()`, a onda 0a não rodou. **Pare e reporte** — as
factories pertencem à T0.0b, e criá-las aqui colide com ela.

- [ ] **Step 3: Escreva a implementação mínima**

Em `app/Models/Device.php`, logo abaixo de `isAvailable()`, acrescente. Note a constante
compartilhada: é ela que garante que método e scope não divirjam na janela de tempo.

```php
/** Janela em que um dispositivo portatec ainda conta como online. */
public const AVAILABILITY_WINDOW_MINUTES = 10;

/**
 * Contrapartida SQL de `isAvailable()`, necessária porque a lista de
 * dispositivos é paginada e não pode ser filtrada em PHP depois do LIMIT.
 * `DeviceAvailabilityScopeTest` garante que os dois concordem.
 */
public function scopeAvailable(Builder $query): Builder
{
    return $query->where(function (Builder $query): void {
        $query->where(function (Builder $query): void {
            $query->where('brand', DeviceBrandEnum::Tuya)->where('tuya_online', true);
        })->orWhere(function (Builder $query): void {
            $query->where('brand', '!=', DeviceBrandEnum::Tuya)
                ->whereNotNull('last_sync')
                ->where('last_sync', '>=', now()->subMinutes(self::AVAILABILITY_WINDOW_MINUTES));
        });
    });
}

public function scopeUnavailable(Builder $query): Builder
{
    return $query->whereNot(fn (Builder $query) => $this->scopeAvailable($query));
}
```

Troque o corpo de `isAvailable()` para consumir a mesma constante:

```php
public function isAvailable(): bool
{
    if ($this->brand === DeviceBrandEnum::Tuya) {
        return (bool) ($this->tuya_online ?? false);
    }

    return $this->last_sync
        ? $this->last_sync->diffInMinutes(now()) < self::AVAILABILITY_WINDOW_MINUTES
        : false;
}
```

Garanta o `use Illuminate\Database\Eloquent\Builder;` no topo do arquivo.

- [ ] **Step 4: Rode o teste e veja passar**

```bash
./vendor/bin/sail test --filter=DeviceAvailabilityScopeTest
```

Esperado: PASS (2 testes).

- [ ] **Step 5: Rode os testes de dispositivo já existentes**

```bash
./vendor/bin/sail test --filter=Device
```

Esperado: PASS. `DeviceTuyaCapabilitiesTest` exercita o mesmo model.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Device.php tests/Unit/DeviceAvailabilityScopeTest.php
git commit -m "feat: adiciona scopes SQL de disponibilidade em Device"
```

---

### ⛔ BARREIRA — fim da onda 0b

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test && ./vendor/bin/sail npm run test:js
```

Tudo verde antes de despachar a onda 1. As telas ainda mostram `nav_bookings_integrations`
como texto cru em dois lugares — é esperado e some na onda 1/2.

---

## ONDA 1 — Ganhos imediatos

Cinco agentes em paralelo. **Pode rodar junto com a onda 2.**

---

### T1.1: Detalhe do local vira hub

Cobre F1.1 (parte local), F1.2, F1.3 (parte local) e F1.8 do spec.

**Files:**
- Modify: `resources/js/pages/places/show.tsx`
- Modify: `app/Http/Controllers/App/PlaceController.php`
- Test: `tests/Feature/Places/PlaceShowHubTest.php` (criar)

- [ ] **Step 1: Escreva os testes que falham**

Criar `tests/Feature/Places/PlaceShowHubTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Places;

use App\Models\Booking;
use App\Models\Integration;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PlaceShowHubTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPlace(): array
    {
        $user = User::factory()->create();
        $place = Place::create(['name' => 'Casa Azul']);
        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'label' => $user->name,
        ]);

        return [$user, $place];
    }

    /**
     * Regressão: o tile usava `place.bookings.length`, e o controller carregava
     * `bookings` com `limit(10)`. A contagem travava em 10 para sempre.
     */
    public function test_booking_count_is_not_capped_at_ten(): void
    {
        [$user, $place] = $this->userWithPlace();

        Booking::factory()->count(13)->create(['place_id' => $place->id]);

        $this->actingAs($user)
            ->get("/app/places/{$place->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('places/show')
                ->where('bookingsCount', 13)
            );
    }

    public function test_place_lists_its_ical_booking_sources(): void
    {
        [$user, $place] = $this->userWithPlace();

        $airbnb = Platform::create(['name' => 'Airbnb', 'slug' => 'airbnb']);
        $ical = Integration::create(['platform_id' => $airbnb->id, 'user_id' => $user->id]);
        $ical->places()->attach($place->id);

        $this->actingAs($user)
            ->get("/app/places/{$place->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('bookingSources', 1)
                ->where('bookingSources.0.id', $ical->id)
            );
    }

    /**
     * A integração Tuya não tem local e não é fonte de reserva. Ela divide a
     * tabela `integrations` com as de iCal, separadas só por `platform.slug`.
     */
    public function test_tuya_integration_is_not_listed_as_a_booking_source(): void
    {
        [$user, $place] = $this->userWithPlace();

        $tuya = Platform::create(['name' => 'Tuya', 'slug' => 'tuya']);
        $tuyaIntegration = Integration::create(['platform_id' => $tuya->id, 'user_id' => $user->id]);
        $tuyaIntegration->places()->attach($place->id);

        $this->actingAs($user)
            ->get("/app/places/{$place->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->has('bookingSources', 0));
    }
}
```

- [ ] **Step 2: Rode os testes e veja falhar**

```bash
./vendor/bin/sail test --filter=PlaceShowHubTest
```

Esperado: FAIL — as props `bookingsCount` e `bookingSources` não existem.

- [ ] **Step 3: Ajuste o controller**

Em `app/Http/Controllers/App/PlaceController.php`, método `show()`, troque o `load` e o
`Inertia::render`. O `abort_unless` **continua onde está**, antes de qualquer leitura de
dado derivado:

```php
$place->load([
    'devices',
    'bookings' => fn ($query) => $query->latest('check_in')->limit(10),
    'accessCodes',
    'placeUsers.user',
    'integrations' => fn ($query) => $query->whereHas(
        'platform',
        fn ($q) => $q->where('slug', '!=', 'tuya')
    )->with('platform'),
]);

abort_unless(
    $place->placeUsers()->where('user_id', Auth::id())->exists(),
    403
);

$place->loadCount('bookings');
```

E no `Inertia::render('places/show', [...])`, acrescente as duas props:

```php
'bookingsCount' => $place->bookings_count,
'bookingSources' => IntegrationResource::collection($place->integrations),
```

Acrescente `use App\Http\Resources\IntegrationResource;` no topo.

- [ ] **Step 4: Rode os testes e veja passar**

```bash
./vendor/bin/sail test --filter=PlaceShowHubTest
```

Esperado: PASS (3 testes).

- [ ] **Step 5: Atualize a página — tiles clicáveis**

Em `resources/js/pages/places/show.tsx`:

**5a** — importe o componente novo e acrescente as props:

```tsx
import { StatTile } from '@/components/stat-tile';
import bookingsRoutes from '@/routes/app/bookings';
import accessCodesRoutes from '@/routes/app/access-codes';
import integrationsRoutes from '@/routes/app/bookings/integrations';
```

Na interface `PlacesShowProps`, acrescente:

```tsx
    bookingsCount: number;
    bookingSources: Integration[];
```

e importe `Integration` de `@/types` junto com `Device` e `Place`.

**5b** — substitua os três blocos de tile pelo grid abaixo:

```tsx
<div className="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-3">
    <StatTile
        label={t('place_devices_heading')}
        value={devices.length}
        href={devicesRoutes.index.url({ query: { place_id: place.id } })}
    />
    <StatTile
        label={t('bookings')}
        value={bookingsCount}
        href={bookingsRoutes.index.url({ query: { place_id: place.id } })}
    />
    <StatTile
        label={t('place_active_codes_heading')}
        value={activeAccessCodes}
        href={accessCodesRoutes.index.url({ query: { place_id: place.id } })}
    />
</div>
```

O rótulo do tile do meio passa de `place_bookings_recent_heading` para `bookings`: ele deixou
de ser "recentes" quando virou contagem total.

- [ ] **Step 6: Acrescente o painel "Fontes de reserva"**

Logo acima do painel de dispositivos, em `places/show.tsx`:

```tsx
<div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
    <div className="flex items-center justify-between border-b border-neutral-200 px-4.5 py-3">
        <span className="text-xs font-bold tracking-wide text-neutral-500 uppercase">
            {t('place_booking_sources_heading')}
        </span>
        <Button asChild size="sm">
            <Link href={integrationsRoutes.create.url({ query: { place_id: place.id } })}>
                {t('place_add_booking_source')}
            </Link>
        </Button>
    </div>
    {bookingSources.length > 0 ? (
        bookingSources.map((source) => (
            <div key={source.id} className="flex items-center gap-3 border-b border-neutral-100 px-4.5 py-3 text-[13.5px] last:border-b-0">
                <Link
                    href={integrationsRoutes.edit.url({ integration: source.id })}
                    className="flex-1 font-semibold text-neutral-900 no-underline hover:text-primary-700"
                >
                    {source.platform?.name ?? t('platform')}
                </Link>
                <span className="text-[12.5px] text-neutral-500">
                    {source.updated_at ? new Date(source.updated_at).toLocaleString('pt-BR') : ''}
                </span>
            </div>
        ))
    ) : (
        <p className="m-0 px-4.5 py-3 text-[13.5px] text-neutral-500">{t('place_no_booking_sources')}</p>
    )}
</div>
```

- [ ] **Step 7: Hierarquize as ações do cabeçalho**

Ainda em `places/show.tsx`, substitua o bloco `actions`. "Controle" fica como botão primário;
o resto vai para o menu `...`:

```tsx
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';

const actions = (
    <>
        <Button asChild>
            <Link href={places.control.url({ place: place.id })}>{t('place_control_action')}</Link>
        </Button>
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" aria-label={t('actions')}>…</Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    <Link href={places.edit.url({ place: place.id })}>{t('place_edit_action')}</Link>
                </DropdownMenuItem>
                {abilities.manageMembers ? (
                    <DropdownMenuItem asChild>
                        <Link href={places.members.url({ place: place.id })}>{t('manage_members')}</Link>
                    </DropdownMenuItem>
                ) : null}
                {abilities.replicate ? (
                    <DropdownMenuItem asChild>
                        <Link href={places.clone.url({ place: place.id })}>{t('clone_place')}</Link>
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    </>
);
```

Se a chave `actions` não existir em `app.php`, use `t('details')`, que existe — **não** edite
o arquivo de tradução: ele pertence à T0.1.

- [ ] **Step 8: Verifique a tela no navegador**

```bash
./vendor/bin/sail npm run dev
```

Abra `/app/places/{id}` e confira: os três tiles navegam para listas filtradas, o painel de
fontes de reserva aparece, e as três ações secundárias estão no menu `...`.

- [ ] **Step 9: Rode os testes de local**

```bash
./vendor/bin/sail test --filter=Place
```

Esperado: PASS, incluindo `PlaceUsersIsolationTest`.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/App/PlaceController.php resources/js/pages/places/show.tsx tests/Feature/Places/PlaceShowHubTest.php
git commit -m "feat: transforma detalhe do local em hub navegavel"
```

---

### T1.2: Tiles do dashboard viram links; quinto tile

Cobre F1.1 (parte dashboard) e F1.4.

**Files:**
- Modify: `resources/js/pages/dashboard.tsx`
- Test: `tests/Feature/DashboardTest.php` (já existe — acrescentar caso)

- [ ] **Step 1: Escreva o teste que falha**

Acrescente a `tests/Feature/DashboardTest.php`:

```php
/**
 * `activeAccessCodes` já era calculado e enviado, e nenhuma tela o renderizava.
 * O quinto tile passa a consumi-lo; este teste fixa a prop no contrato.
 */
public function test_dashboard_sends_active_access_codes_count(): void
{
    $user = User::factory()->create();
    $place = Place::create(['name' => 'Casa Azul']);
    PlaceUser::create([
        'place_id' => $place->id,
        'user_id' => $user->id,
        'role' => 'admin',
        'label' => $user->name,
    ]);

    AccessCode::create([
        'place_id' => $place->id,
        'user_id' => $user->id,
        'pin' => '123456',
        'start' => now()->subDay(),
        'end' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->get('/app/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->where('activeAccessCodes', 1)
        );
}
```

Ajuste os `use` do arquivo se `AccessCode` ainda não estiver importado.

- [ ] **Step 2: Rode o teste**

```bash
./vendor/bin/sail test --filter=DashboardTest
```

Esperado: PASS já de cara — o backend **já** envia a prop. Este teste é uma rede de
proteção: ele impede que alguém remova a prop ao ver que "ninguém usa".

- [ ] **Step 3: Substitua os tiles pelo componente compartilhado**

Em `resources/js/pages/dashboard.tsx`, importe:

```tsx
import { StatTile } from '@/components/stat-tile';
import accessCodes from '@/routes/app/access-codes';
import devicesRoutes from '@/routes/app/devices';
```

Acrescente `activeAccessCodes` à desestruturação de props (a interface já o declara, só a
desestruturação o omite hoje).

Substitua o grid inteiro de quatro tiles por:

```tsx
const today = new Date().toISOString().slice(0, 10);

<div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
    <StatTile
        label={t('dashboard_devices_online_heading')}
        value={`${totalOnline}/${totalDevices}`}
        href={devicesRoutes.index.url({ query: { status: 'online' } })}
    />
    <StatTile
        label={t('dashboard_devices_offline_heading')}
        value={totalOffline}
        tone={totalOffline > 0 ? 'error' : 'default'}
        href={devicesRoutes.index.url({ query: { status: 'offline' } })}
    />
    <StatTile
        label={t('dashboard_active_bookings_heading')}
        value={activeBookings}
        href={bookings.index.url({ query: { status: 'current' } })}
    />
    <StatTile
        label={t('dashboard_today_checkins_heading')}
        value={todayCheckIns}
        href={bookings.index.url({ query: { date_from: today, date_to: today } })}
    />
    <StatTile
        label={t('dashboard_active_codes_heading')}
        value={activeAccessCodes}
        href={accessCodes.index.url({ query: { status: 'active' } })}
    />
</div>
```

O import `cn` pode ficar órfão depois da troca — remova-o se o `lint` acusar.

- [ ] **Step 4: Verifique no navegador**

Abra `/app/dashboard`. Os cinco tiles navegam. O tile "Offline" só terá destino funcional
depois da T1.3 (filtro `status`); até lá ele leva à lista sem filtro, o que é aceitável e
se resolve na mesma onda.

- [ ] **Step 5: Rode os testes**

```bash
./vendor/bin/sail test --filter=DashboardTest
```

Esperado: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/dashboard.tsx tests/Feature/DashboardTest.php
git commit -m "feat: torna tiles do dashboard navegaveis e adiciona codigos ativos"
```

---

### T1.3: Filtro de status na lista de dispositivos

Cobre F1.5. Depende dos scopes criados em T0.5.

**Files:**
- Modify: `app/Http/Controllers/App/DeviceController.php`
- Modify: `resources/js/pages/devices/index.tsx`
- Test: `tests/Feature/Devices/DeviceStatusFilterTest.php` (criar)

- [ ] **Step 1: Escreva o teste que falha**

Criar `tests/Feature/Devices/DeviceStatusFilterTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Enums\DeviceBrandEnum;
use App\Models\Device;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DeviceStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPlace(): array
    {
        $user = User::factory()->create();
        $place = Place::create(['name' => 'Casa Azul']);
        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'label' => $user->name,
        ]);

        return [$user, $place];
    }

    public function test_status_offline_returns_only_unavailable_devices(): void
    {
        [$user, $place] = $this->userWithPlace();

        $online = Device::factory()->create([
            'place_id' => $place->id,
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now()->subMinute(),
        ]);
        $offline = Device::factory()->create([
            'place_id' => $place->id,
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->get('/app/devices?status=offline')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('devices/index')
                ->where('filters.status', 'offline')
                ->has('devices.data', 1)
                ->where('devices.data.0.id', $offline->id)
            );

        $this->actingAs($user)
            ->get('/app/devices?status=online')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('devices.data', 1)
                ->where('devices.data.0.id', $online->id)
            );
    }

    public function test_without_status_all_devices_are_returned(): void
    {
        [$user, $place] = $this->userWithPlace();

        Device::factory()->count(2)->create([
            'place_id' => $place->id,
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->get('/app/devices')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('devices.data', 2));
    }

    /**
     * O filtro não pode furar o escopo por place: um status na query string não
     * dá acesso a dispositivo de outra conta.
     */
    public function test_status_filter_does_not_leak_devices_from_other_users(): void
    {
        [$user] = $this->userWithPlace();

        $otherPlace = Place::create(['name' => 'De outro']);
        Device::factory()->create([
            'place_id' => $otherPlace->id,
            'brand' => DeviceBrandEnum::Portatec,
            'last_sync' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->get('/app/devices?status=offline')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('devices.data', 0));
    }
}
```

- [ ] **Step 2: Rode os testes e veja falhar**

```bash
./vendor/bin/sail test --filter=DeviceStatusFilterTest
```

Esperado: FAIL — não existe prop `filters` nem filtro `status`.

- [ ] **Step 3: Ajuste o controller**

Em `DeviceController@index`, depois da leitura de `$search`:

```php
$status = (string) $request->query('status', '');
```

Na construção da query, **depois** do `when` de `place_id` e **antes** da paginação:

```php
->when($status === 'online', fn (Builder $query) => $query->available())
->when($status === 'offline', fn (Builder $query) => $query->unavailable())
```

E no `Inertia::render('devices/index', [...])`, troque as props soltas `search`/`placeId`
por um objeto `filters`, mantendo as duas antigas para não quebrar nada que ainda as leia:

```php
'filters' => [
    'place_id' => $placeFilter,
    'search' => $search,
    'status' => $status,
],
```

- [ ] **Step 4: Rode os testes e veja passar**

```bash
./vendor/bin/sail test --filter=DeviceStatusFilterTest
```

Esperado: PASS (3 testes).

- [ ] **Step 5: Acrescente o campo na tela**

Em `resources/js/pages/devices/index.tsx`, acrescente `filters` à interface de props e
inclua o campo novo no `FilterBar`, depois do campo de local:

```tsx
{
    type: 'select',
    key: 'status',
    label: t('devices_status_label'),
    options: [
        { value: '', label: t('all') },
        { value: 'online', label: t('devices_status_online') },
        { value: 'offline', label: t('devices_status_offline') },
    ],
},
```

e acrescente `status: filters.status` ao objeto `values`. Se `t('all')` não existir, use
`t('booking_source_option_all')`, que existe — **não** edite o arquivo de tradução.

- [ ] **Step 6: Verifique no navegador**

Abra `/app/devices?status=offline` e confira que a lista e o select refletem o filtro. Volte
ao `/app/dashboard` e clique no tile "Offline": deve cair na lista já filtrada.

- [ ] **Step 7: Rode os testes de dispositivo**

```bash
./vendor/bin/sail test --filter=Device
```

Esperado: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/App/DeviceController.php resources/js/pages/devices/index.tsx tests/Feature/Devices/DeviceStatusFilterTest.php
git commit -m "feat: adiciona filtro de status na lista de dispositivos"
```

---

### T1.4: Reserva ↔ código de acesso, e rótulos

Cobre F1.6, F1.7 e a parte de F1.9 que vive nas páginas de reserva.

**Nota de correção ao spec:** F1.6 dizia "exige expor `booking_id` em `AccessCodeResource`".
Ele **já está exposto** — confira `app/Http/Resources/AccessCodeResource.php`. Não há
mudança de backend nesta tarefa; ela é inteiramente de frontend.

**Files:**
- Modify: `resources/js/pages/access-codes/index.tsx`
- Modify: `resources/js/pages/bookings/show.tsx`
- Modify: `resources/js/pages/bookings/index.tsx`
- Test: `tests/Feature/AccessCodes/AccessCodeBookingLinkTest.php` (criar)

- [ ] **Step 1: Escreva o teste que falha**

Criar `tests/Feature/AccessCodes/AccessCodeBookingLinkTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\AccessCodes;

use App\Models\AccessCode;
use App\Models\Booking;
use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * As duas telas passam a navegar uma para a outra. O par só é montável se as
 * duas pontas do vínculo chegarem nas props.
 */
class AccessCodeBookingLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_code_list_exposes_the_origin_booking(): void
    {
        $user = User::factory()->create();
        $place = Place::create(['name' => 'Casa Azul']);
        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'label' => $user->name,
        ]);

        $booking = Booking::factory()->create(['place_id' => $place->id]);
        $code = AccessCode::where('booking_id', $booking->id)->first()
            ?? AccessCode::create([
                'place_id' => $place->id,
                'user_id' => $user->id,
                'booking_id' => $booking->id,
                'pin' => '654321',
                'start' => now()->subDay(),
                'end' => now()->addDay(),
            ]);

        $this->actingAs($user)
            ->get('/app/access-codes')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('access-codes/index')
                ->where('accessCodes.data.0.booking_id', $booking->id)
            );

        $this->actingAs($user)
            ->get("/app/bookings/{$booking->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('bookings/show')
                ->where('booking.access_code.id', $code->id)
            );
    }
}
```

O `?? AccessCode::create(...)` cobre os dois mundos: o `BookingObserver` pode já ter criado
o código ao salvar a reserva.

- [ ] **Step 2: Rode o teste**

```bash
./vendor/bin/sail test --filter=AccessCodeBookingLinkTest
```

Se falhar em `booking.access_code.id`, `BookingController@show` não está carregando a
relação: acrescente `$booking->load('accessCode')` antes do `Inertia::render` e rode de novo.
Esse é o único toque de backend possível nesta tarefa.

- [ ] **Step 3: Ligue o PIN da reserva ao código**

Em `resources/js/pages/bookings/show.tsx`, substitua a linha do PIN:

```tsx
<p className="m-0">
    <strong>{t('booking_show_pin_label')}:</strong>{' '}
    {booking.access_code ? (
        <Link
            href={accessCodes.edit.url({ accessCode: booking.access_code.id })}
            className="font-mono font-bold text-primary-500 no-underline hover:text-primary-700"
        >
            {booking.access_code.pin}
        </Link>
    ) : (
        t('booking_show_pin_not_generated')
    )}
</p>
```

com `import accessCodes from '@/routes/app/access-codes';` no topo.

- [ ] **Step 4: Ligue o código à reserva de origem**

Em `resources/js/pages/access-codes/index.tsx`, o `display_name` passa a ser link quando há
reserva:

```tsx
{accessCode.booking_id ? (
    <Link
        href={bookingsRoutes.show.url({ booking: accessCode.booking_id })}
        className="m-0 mt-0.5 block text-[12.5px] text-primary-700 no-underline hover:text-primary-500"
    >
        {accessCode.display_name}
    </Link>
) : (
    <p className="m-0 mt-0.5 text-[12.5px] text-neutral-500">{accessCode.display_name}</p>
)}
```

com `import bookingsRoutes from '@/routes/app/bookings';` no topo. Garanta que `booking_id`
esteja no tipo `AccessCode` em `resources/js/types` — se não estiver, acrescente
`booking_id: number | null;`.

- [ ] **Step 5: Troque "Detalhes" por "Editar" nos códigos**

Ainda em `access-codes/index.tsx`, o link da direita passa a usar `t('edit')`, que já existe:

```tsx
<Link
    href={accessCodes.edit.url({ accessCode: accessCode.id })}
    className="ml-auto flex-shrink-0 text-[12.5px] font-semibold text-primary-700 no-underline hover:text-primary-500"
>
    {t('edit')}
</Link>
```

- [ ] **Step 6: Corrija o rótulo do cabeçalho de Reservas**

Em `resources/js/pages/bookings/index.tsx`, no `headerActions`, troque
`t('nav_bookings_integrations')` por `t('integrations')`. É o que dá aos dois cabeçalhos de
integração o mesmo texto — a simetria decidida na seção 2.4 do spec — e fecha a janela
aberta pela remoção da chave em T0.1.

- [ ] **Step 7: Rode os testes**

```bash
./vendor/bin/sail test --filter=AccessCode
```

Esperado: PASS.

- [ ] **Step 8: Verifique no navegador**

Abra uma reserva com PIN: o PIN leva ao código. Abra `/app/access-codes`: o nome do hóspede
leva à reserva, e a ação da direita diz "Editar".

- [ ] **Step 9: Commit**

```bash
git add resources/js/pages/access-codes/index.tsx resources/js/pages/bookings/show.tsx resources/js/pages/bookings/index.tsx tests/Feature/AccessCodes/AccessCodeBookingLinkTest.php
git commit -m "feat: liga reserva e codigo de acesso nos dois sentidos"
```

Se o Step 2 exigiu o `load('accessCode')`, inclua `app/Http/Controllers/App/BookingController.php`.

---

### T1.5: Criar fonte de reserva já com o local escolhido

Cobre a parte de F1.3 que vive na tela de criação de integração. É o destino do botão
"Adicionar fonte" que T1.1 acrescenta.

**Files:**
- Modify: `app/Http/Controllers/App/IntegrationController.php`
- Modify: `resources/js/pages/integrations/create.tsx`
- Test: `tests/Feature/Integrations/IntegrationCreatePrefillTest.php` (criar)

- [ ] **Step 1: Escreva o teste que falha**

Criar `tests/Feature/Integrations/IntegrationCreatePrefillTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class IntegrationCreatePrefillTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPlaces(): array
    {
        $user = User::factory()->create();
        Platform::create(['name' => 'Airbnb', 'slug' => 'airbnb']);

        // Nomes escolhidos para que "Alfa" seja o primeiro na ordenação: sem o
        // parâmetro, é ele que vem pré-selecionado.
        $first = Place::create(['name' => 'Alfa']);
        $second = Place::create(['name' => 'Beta']);

        foreach ([$first, $second] as $place) {
            PlaceUser::create([
                'place_id' => $place->id,
                'user_id' => $user->id,
                'role' => 'admin',
                'label' => $user->name,
            ]);
        }

        return [$user, $first, $second];
    }

    public function test_place_id_in_the_query_preselects_that_place(): void
    {
        [$user, , $second] = $this->userWithPlaces();

        $this->actingAs($user)
            ->get("/app/bookings/integrations/create?place_id={$second->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('integrations/create')
                ->where('placeId', $second->id)
            );
    }

    public function test_without_the_parameter_the_first_place_is_preselected(): void
    {
        [$user, $first] = $this->userWithPlaces();

        $this->actingAs($user)
            ->get('/app/bookings/integrations/create')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('placeId', $first->id));
    }

    /**
     * O parâmetro é conveniência de navegação, não autorização: um local de
     * outra conta é ignorado, e cai no padrão.
     */
    public function test_a_place_from_another_account_is_ignored(): void
    {
        [$user, $first] = $this->userWithPlaces();
        $foreign = Place::create(['name' => 'De outro']);

        $this->actingAs($user)
            ->get("/app/bookings/integrations/create?place_id={$foreign->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('placeId', $first->id));
    }
}
```

- [ ] **Step 2: Rode os testes e veja falhar**

```bash
./vendor/bin/sail test --filter=IntegrationCreatePrefillTest
```

Esperado: FAIL no primeiro caso — hoje `placeId` é sempre `places.first()->id`.

- [ ] **Step 3: Escreva a implementação mínima**

Em `IntegrationController@create`, substitua a linha de `placeId` do `Inertia::render`. A
validação contra a coleção já carregada é o que faz o terceiro teste passar:

```php
$requestedPlaceId = $request->integer('place_id');
$selectedPlaceId = $places->contains('id', $requestedPlaceId)
    ? $requestedPlaceId
    : $places->first()?->id;

return Inertia::render('integrations/create', [
    'platforms' => PlatformResource::collection($platforms),
    'places' => PlaceResource::collection($places),
    'platformId' => $platforms->first()?->id,
    'placeId' => $selectedPlaceId,
]);
```

- [ ] **Step 4: Rode os testes e veja passar**

```bash
./vendor/bin/sail test --filter=IntegrationCreatePrefillTest
```

Esperado: PASS (3 testes).

- [ ] **Step 5: Confirme que a tela respeita a prop**

Leia `resources/js/pages/integrations/create.tsx`. Se o `useForm` já inicializa o campo de
local com a prop `placeId`, **não mude nada**. Se ele ignora a prop e usa um padrão próprio,
troque a inicialização para usar `placeId`.

- [ ] **Step 6: Verifique no navegador**

Abra `/app/bookings/integrations/create?place_id={id}` e confira que o select de local já
vem no local certo.

- [ ] **Step 7: Rode os testes de integração**

```bash
./vendor/bin/sail test --filter=Integration
```

Esperado: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/App/IntegrationController.php resources/js/pages/integrations/create.tsx tests/Feature/Integrations/IntegrationCreatePrefillTest.php
git commit -m "feat: pre-seleciona local ao criar fonte de reserva"
```

---

### ⛔ BARREIRA — fim da onda 1

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test && ./vendor/bin/sail npm run test:js
```

---

## ONDA 2 — Layout

**Um agente só.** As duas tarefas editam `app-layout.tsx` e são sequenciais entre si. Pode
rodar em paralelo com a onda 1 inteira.

---

### T2.1: Sidebar em grupos e menu de usuário

Cobre F2 do spec.

**Files:**
- Modify: `resources/js/layouts/app-layout.tsx`
- Modify: `resources/js/components/__tests__/nav-link.test.ts`

- [ ] **Step 1: Atualize o teste de navegação**

Em `resources/js/components/__tests__/nav-link.test.ts`, **acrescente** o caso novo. Os
testes existentes de `exclude` **permanecem** — a capacidade segue na API, só deixa de ser
usada pelo layout:

```ts
/**
 * Com "Integrações iCal" fora da sidebar, o item Reservas acender em
 * /app/bookings/integrations passa a ser o comportamento correto: aquela tela é
 * uma sub-página de Reservas. O `exclude` continua existindo para o dia em que
 * um item de menu voltar a aninhar sob outro.
 */
it('marca Reservas nas integrações iCal quando não há exclusão', () => {
    expect(isNavLinkActive('/app/bookings/integrations', '/app/bookings*')).toBe(true);
    expect(isNavLinkActive('/app/bookings/integrations/create', '/app/bookings*')).toBe(true);
});
```

- [ ] **Step 2: Rode o teste**

```bash
./vendor/bin/sail npm run test:js -- nav-link
```

Esperado: PASS — `isNavLinkActive` já se comporta assim sem `exclude`. O teste documenta a
decisão e trava o comportamento.

- [ ] **Step 3: Reorganize os itens em grupos**

Em `resources/js/layouts/app-layout.tsx`, substitua a constante `items` por grupos. **Não**
inclua o item "Controle" ainda: a rota `/app/control` só nasce na onda 5, e um item de menu
que dá 404 é pior do que um item ausente.

```tsx
const groups = [
    {
        label: t('nav_group_operation'),
        items: [
            { href: app.dashboard.url(), pattern: '/app/dashboard', label: t('nav_dashboard'), icon: <DashboardIcon /> },
            { href: app.bookings.index.url(), pattern: '/app/bookings*', label: t('nav_bookings'), icon: <BookingsIcon /> },
            { href: app.accessCodes.index.url(), pattern: '/app/access-codes*', label: t('nav_access_codes'), icon: <AccessCodesIcon /> },
        ],
    },
    {
        label: t('nav_group_setup'),
        items: [
            { href: app.places.index.url(), pattern: '/app/places*', label: t('nav_places'), icon: <PlacesIcon /> },
            { href: app.devices.index.url(), pattern: '/app/devices*', label: t('nav_devices'), icon: <DevicesIcon /> },
        ],
    },
];

const allItems = groups.flatMap((group) => group.items);
```

Três remoções decorrentes: o item de Integrações iCal, a propriedade `exclude` do item de
Reservas, e o item "Admin" da navegação (ele passa para o `UserMenu`). `IntegrationsIcon` e
`AdminIcon` ficam órfãos — apague os dois componentes de ícone.

- [ ] **Step 4: Renderize os grupos**

Substitua o `<nav>` por:

```tsx
<nav className="flex flex-col gap-5">
    {groups.map((group) => (
        <div key={group.label} className="flex flex-col gap-0.5">
            <span className="px-2.5 pb-1 text-[10.5px] font-bold tracking-wider text-neutral-500 uppercase">
                {group.label}
            </span>
            {group.items.map((item) => (
                <NavLink
                    key={item.href}
                    href={item.href}
                    pattern={item.pattern}
                    onClick={closeMenu}
                    className={cn(
                        NAV_ITEM_CLASS,
                        isNavLinkActive(pathname, item.pattern)
                            ? 'bg-primary-500/20 text-primary-300'
                            : 'text-neutral-400 hover:text-neutral-100',
                    )}
                >
                    {item.icon}
                    {item.label}
                </NavLink>
            ))}
        </div>
    ))}
</nav>
```

E corrija o cálculo do rótulo ativo, que agora itera sobre `allItems`:

```tsx
const activeItem = allItems.find((item) => isNavLinkActive(pathname, item.pattern));
const crumb = activeItem?.label ?? t('nav_dashboard');
```

- [ ] **Step 5: Ponha o menu de usuário no rodapé**

Importe `import { UserMenu } from '@/components/user-menu';` e substitua o bloco de logout
`lg:hidden` que existe hoje dentro do `<aside>` por:

```tsx
<UserMenu
    name={auth.user?.name ?? ''}
    email={auth.user?.email ?? ''}
    isSuperAdmin={canAccessAdminPanel}
    onNavigate={closeMenu}
/>
```

`name` e `email` já vêm em `auth.user` por `HandleInertiaRequests` — acrescente os dois campos
à interface `AppLayoutPageProps`, que hoje declara só `is_super_admin`.

- [ ] **Step 6: Limpe as barras superiores**

Na barra do desktop, remova o link "Admin" e o botão "Sair" — sobra só o `<span>` do crumb à
esquerda. Na barra do mobile, remova o `<Link>` de logout à direita; sobram o hambúrguer e o
título, que é o espaço de que o breadcrumb precisa em T2.2.

`LogoutIcon` fica órfã no layout — apague-a (o `UserMenu` não a usa).

- [ ] **Step 7: Verifique no navegador**

```bash
./vendor/bin/sail npm run dev
```

Confira: dois grupos rotulados; nenhum item de Integrações iCal; nome e e-mail no rodapé;
"Admin" só para super admin; logout funciona no desktop e no mobile pela gaveta; navegar para
`/app/bookings/integrations` acende **Reservas**.

- [ ] **Step 8: Rode os testes**

```bash
./vendor/bin/sail npm run test:js && ./vendor/bin/sail test --filter=InertiaSharedProps
```

Esperado: PASS.

- [ ] **Step 9: Commit**

```bash
git add resources/js/layouts/app-layout.tsx resources/js/components/__tests__/nav-link.test.ts
git commit -m "feat: agrupa sidebar e move conta para o rodape do menu"
```

---

### T2.2: Breadcrumb real no layout

Cobre F3 do spec (integração; a adoção por tela é a onda 3).

**Files:**
- Modify: `resources/js/layouts/app-layout.tsx`

- [ ] **Step 1: Aceite a prop e mantenha o fallback**

Em `app-layout.tsx`, importe o componente da T0.3 e amplie a interface:

```tsx
import { Breadcrumbs, type Crumb } from '@/components/breadcrumbs';

export interface AppLayoutProps {
    children: ReactNode;
    /**
     * Trilha da página. Sem ela, o layout cai no rótulo da seção ativa — o
     * comportamento anterior — para que a adoção seja incremental e nenhuma
     * tela quebre enquanto a onda 3 não passa por todas.
     */
    breadcrumbs?: Crumb[];
}
```

E na assinatura: `export function AppLayout({ children, breadcrumbs }: AppLayoutProps)`.

Logo depois do cálculo de `crumb`:

```tsx
const trail: Crumb[] = breadcrumbs ?? [{ label: crumb }];
const currentLabel = trail[trail.length - 1]?.label ?? crumb;
const parent = trail.length > 1 ? trail[trail.length - 2] : undefined;
```

- [ ] **Step 2: Use a trilha na barra do desktop**

Substitua o `<span>` de "Portatec / …" por:

```tsx
<Breadcrumbs items={[{ label: 'Portatec', href: app.dashboard.url() }, ...trail]} />
```

- [ ] **Step 3: Use a trilha na barra do mobile**

O mobile mostra a página atual, não a seção. Quando existe um pai com `href`, ele vira um
chevron de voltar. Substitua o `<span>` do título por:

```tsx
{parent?.href ? (
    <Link
        href={parent.href}
        aria-label={parent.label}
        className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg text-white no-underline"
    >
        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M15 5 L8 12 L15 19" />
        </svg>
    </Link>
) : null}
<span className="min-w-0 flex-1 truncate text-[15px] font-bold text-white">{currentLabel}</span>
```

- [ ] **Step 4: Verifique no navegador**

Sem nenhuma página passando `breadcrumbs` ainda, tudo deve continuar exatamente como antes:
desktop mostra "Portatec / Locais", mobile mostra "Locais". Esse é o fallback funcionando.

- [ ] **Step 5: Rode os testes**

```bash
./vendor/bin/sail npm run test:js
```

Esperado: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/layouts/app-layout.tsx
git commit -m "feat: aceita trilha de breadcrumb no layout, com fallback"
```

---

### ⛔ BARREIRA — fim da onda 2

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test && ./vendor/bin/sail npm run test:js
```

---

## ONDA 3 — Adoção do breadcrumb

Quatro agentes em paralelo, um por família de páginas. Todos seguem o **mesmo padrão**:
acrescentar a prop `breadcrumbs` ao `<AppLayout>` da página, com a trilha da tabela do spec.

**Padrão comum a todas as tarefas desta onda** — exemplo em `places/members.tsx`:

```tsx
import places from '@/routes/app/places';

<AppLayout
    breadcrumbs={[
        { label: t('nav_places'), href: places.index.url() },
        { label: place.name, href: places.show.url({ place: place.id }) },
        { label: t('manage_members') },
    ]}
>
```

Regras: o último item **nunca** leva `href`; use sempre chaves de tradução já existentes
(nenhuma tarefa desta onda edita `app.php`); `PageHeader backHref` **permanece** onde já está.

Verificação, igual para as quatro: abrir cada rota da sua lista e conferir que o desktop
mostra a trilha inteira e o mobile mostra o título da página com o chevron do pai.

Commit, ao fim de cada tarefa:

```bash
git add <apenas os arquivos da sua lista>
git commit -m "feat: adiciona trilha de breadcrumb nas telas de <familia>"
```

---

### T3.1: Trilhas das telas de local

**Files:** `resources/js/pages/places/show.tsx`, `edit.tsx`, `members.tsx`, `clone.tsx`,
`attach-device.tsx`, `control.tsx`

- [ ] **Step 1:** `show.tsx` → `Locais / {place.name}`
- [ ] **Step 2:** `edit.tsx` → `Locais / {place.name} / Editar` (`t('edit')`)
- [ ] **Step 3:** `members.tsx` → `Locais / {place.name} / Membros` (`t('members')`)
- [ ] **Step 4:** `clone.tsx` → `Locais / {place.name} / Clonar` (`t('clone_place')`)
- [ ] **Step 5:** `attach-device.tsx` → `Locais / {place.name} / Adicionar dispositivo` (`t('attach_device')`)
- [ ] **Step 6:** `control.tsx` → `Locais / {place.name} / Controle` (`t('nav_control')`)
- [ ] **Step 7:** verifique as seis rotas no navegador
- [ ] **Step 8:** commit

Em `control.tsx` a trilha começa em Locais e não em Controle: a onda 5 é que cria a seção
Controle. Quando ela existir, esta trilha migra junto na T5.2.

---

### T3.2: Trilhas das telas de dispositivo

**Files:** `resources/js/pages/devices/show.tsx`, `edit.tsx`, `control.tsx`,
`integrations/index.tsx`, `integrations/tuya-connect.tsx`

- [ ] **Step 1:** `show.tsx` → `Dispositivos / {device.name}`
- [ ] **Step 2:** `edit.tsx` → `Dispositivos / {device.name} / Editar`
- [ ] **Step 3:** `control.tsx` → `Dispositivos / {device.name} / Controle`
- [ ] **Step 4:** `integrations/index.tsx` → `Dispositivos / Integrações` (`t('integrations')`)
- [ ] **Step 5:** `integrations/tuya-connect.tsx` → `Dispositivos / Integrações / Conectar Tuya` (`t('tuya_connect_action')`)
- [ ] **Step 6:** verifique as cinco rotas no navegador
- [ ] **Step 7:** commit

---

### T3.3: Trilhas das telas de reserva e integração iCal

**Files:** `resources/js/pages/bookings/show.tsx`, `create.tsx`,
`integrations/index.tsx`, `create.tsx`, `edit.tsx`

- [ ] **Step 1:** `bookings/show.tsx` → `Reservas / Reserva #{booking.id}`
- [ ] **Step 2:** `bookings/create.tsx` → `Reservas / Nova reserva` (`t('new_booking')`)
- [ ] **Step 3:** `integrations/index.tsx` → `Reservas / Integrações` (`t('integrations')`)
- [ ] **Step 4:** `integrations/create.tsx` → `Reservas / Integrações / Nova` (`t('integration_new_action')`)
- [ ] **Step 5:** `integrations/edit.tsx` → `Reservas / Integrações / Editar` (`t('edit')`)
- [ ] **Step 6:** verifique as cinco rotas no navegador
- [ ] **Step 7:** commit

A trilha das integrações iCal começar em **Reservas** é a expressão da decisão 2.4 do spec:
elas são sub-navegação de Reservas, não uma seção própria.

---

### T3.4: Trilhas das telas de código de acesso

**Files:** `resources/js/pages/access-codes/create.tsx`, `edit.tsx`

- [ ] **Step 1:** `create.tsx` → `Códigos de acesso / Novo` (`t('new_access_code')`)
- [ ] **Step 2:** `edit.tsx` → `Códigos de acesso / Editar` (`t('edit')`)
- [ ] **Step 3:** verifique as duas rotas no navegador
- [ ] **Step 4:** commit

---

### ⛔ BARREIRA — fim da onda 3

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test && ./vendor/bin/sail npm run test:js
```

---

## ONDA 4 — Local atual

**T4.1 roda sozinha primeiro.** Só quando ela commitar é que T4.2–T4.6 partem em paralelo.

---

### T4.1: Serviço, rota e props compartilhadas

Cobre F4.1 a F4.4 do spec.

**Files:**
- Create: `app/Services/CurrentPlaceService.php`
- Create: `app/Http/Controllers/App/SetCurrentPlaceController.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CurrentPlaceTest.php` (criar)

- [ ] **Step 1: Escreva os testes que falham**

Criar `tests/Feature/CurrentPlaceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use App\Services\CurrentPlaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CurrentPlaceTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPlace(string $name = 'Casa Azul'): array
    {
        $user = User::factory()->create();
        $place = Place::create(['name' => $name]);
        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'label' => $user->name,
        ]);

        return [$user, $place];
    }

    public function test_setting_and_reading_the_current_place(): void
    {
        [$user, $place] = $this->userWithPlace();
        $this->actingAs($user);

        app(CurrentPlaceService::class)->set($user, $place->id);

        $this->assertSame($place->id, app(CurrentPlaceService::class)->get($user)?->id);
    }

    public function test_null_clears_the_selection(): void
    {
        [$user, $place] = $this->userWithPlace();
        $this->actingAs($user);

        $service = app(CurrentPlaceService::class);
        $service->set($user, $place->id);
        $service->set($user, null);

        $this->assertNull($service->get($user));
    }

    public function test_a_place_from_another_account_is_rejected(): void
    {
        [$user] = $this->userWithPlace();
        $foreign = Place::create(['name' => 'De outro']);
        $this->actingAs($user);

        app(CurrentPlaceService::class)->set($user, $foreign->id);

        $this->assertNull(app(CurrentPlaceService::class)->get($user));
    }

    /**
     * O ponto crítico de segurança: sem revalidar o vínculo a cada leitura, um
     * usuário removido de um local continuaria vendo os dados dele até trocar de
     * seleção. Ver `PlaceUsersIsolationTest` para a mesma classe de defeito.
     */
    public function test_losing_access_to_the_place_clears_the_selection(): void
    {
        [$user, $place] = $this->userWithPlace();
        $this->actingAs($user);

        app(CurrentPlaceService::class)->set($user, $place->id);

        PlaceUser::where('place_id', $place->id)->where('user_id', $user->id)->delete();

        $this->assertNull(app(CurrentPlaceService::class)->get($user->fresh()));
        $this->assertFalse(session()->has('current_place_id'));
    }

    public function test_the_route_updates_the_selection(): void
    {
        [$user, $place] = $this->userWithPlace();

        $this->actingAs($user)
            ->from('/app/bookings')
            ->post('/app/current-place', ['place_id' => $place->id])
            ->assertRedirect('/app/bookings');

        $this->assertSame($place->id, session('current_place_id'));
    }

    public function test_shared_props_expose_the_current_place_and_the_place_list(): void
    {
        [$user, $place] = $this->userWithPlace();

        $this->actingAs($user)->post('/app/current-place', ['place_id' => $place->id]);

        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currentPlace.id', $place->id)
                ->where('currentPlace.name', 'Casa Azul')
                ->has('places', 1)
            );
    }

    /**
     * Regra de precedência (spec 4.4): um place_id explícito e válido na URL
     * atualiza a sessão, para que o seletor nunca mostre um local diferente do
     * que a lista está exibindo.
     */
    public function test_an_explicit_place_id_in_the_url_updates_the_session(): void
    {
        [$user, $place] = $this->userWithPlace();

        $this->actingAs($user)->get("/app/bookings?place_id={$place->id}");

        $this->assertSame($place->id, session('current_place_id'));
    }
}
```

O último caso só passa depois da T4.2. Deixe-o falhando e diga isso no relatório — a T4.2 o
fecha.

- [ ] **Step 2: Rode os testes e veja falhar**

```bash
./vendor/bin/sail test --filter=CurrentPlaceTest
```

Esperado: FAIL — `Target class [App\Services\CurrentPlaceService] does not exist`.

- [ ] **Step 3: Escreva o serviço**

Criar `app/Services/CurrentPlaceService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Place;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * O "local atual" é uma conveniência de navegação guardada em sessão. Ele NÃO é
 * fronteira de segurança: o escopo real continua sendo o `whereIn('place_id',
 * $userPlaceIds)` de cada consulta. Ainda assim, `get()` revalida o vínculo a
 * cada leitura — sem isso, quem perdesse acesso a um local continuaria vendo os
 * dados dele até trocar de seleção.
 */
class CurrentPlaceService
{
    public const SESSION_KEY = 'current_place_id';

    public function get(User $user): ?Place
    {
        $placeId = session(self::SESSION_KEY);

        if ($placeId === null) {
            return null;
        }

        $place = Place::query()
            ->whereKey($placeId)
            ->whereHas('placeUsers', fn ($query) => $query->where('user_id', $user->id))
            ->first();

        if ($place === null) {
            session()->forget(self::SESSION_KEY);
        }

        return $place;
    }

    public function set(User $user, ?int $placeId): void
    {
        if ($placeId === null) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        $belongs = $user->placeUsers()->where('place_id', $placeId)->exists();

        if (! $belongs) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        session([self::SESSION_KEY => $placeId]);
    }

    /**
     * Regra de precedência do spec 4.4: um `place_id` explícito e válido na query
     * string atualiza a sessão antes de filtrar. É isso que mantém o seletor
     * dizendo a verdade sobre o que está na tela, e que faz os links diretos do
     * dashboard e dos tiles continuarem funcionando.
     */
    public function resolveForRequest(Request $request, User $user): ?int
    {
        if ($request->filled('place_id')) {
            $this->set($user, $request->integer('place_id'));
        }

        return $this->get($user)?->id;
    }
}
```

- [ ] **Step 4: Escreva o controller da rota**

Criar `app/Http/Controllers/App/SetCurrentPlaceController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\CurrentPlaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SetCurrentPlaceController extends Controller
{
    public function __invoke(Request $request, CurrentPlaceService $currentPlace): RedirectResponse
    {
        $placeId = $request->filled('place_id') ? $request->integer('place_id') : null;

        $currentPlace->set(Auth::user(), $placeId);

        return redirect()->back();
    }
}
```

- [ ] **Step 5: Registre a rota**

Em `routes/web.php`, dentro do grupo `auth` com prefixo `app` e nome `app.`, junto das
outras rotas de sessão:

```php
Route::post('/current-place', SetCurrentPlaceController::class)->name('current-place.update');
```

com `use App\Http\Controllers\App\SetCurrentPlaceController;` no topo.

- [ ] **Step 6: Compartilhe as props**

Em `app/Http/Middleware/HandleInertiaRequests.php`, dentro do array de `share()`:

```php
'currentPlace' => function () use ($user): ?array {
    if ($user === null) {
        return null;
    }

    $place = app(CurrentPlaceService::class)->get($user);

    return $place === null ? null : ['id' => $place->id, 'name' => $place->name];
},
'places' => function () use ($user): array {
    if ($user === null) {
        return [];
    }

    return Place::query()
        ->whereHas('placeUsers', fn ($query) => $query->where('user_id', $user->id))
        ->orderBy('name')
        ->get(['id', 'name'])
        ->map(fn (Place $place): array => ['id' => $place->id, 'name' => $place->name])
        ->all();
},
```

com `use App\Models\Place;` e `use App\Services\CurrentPlaceService;` no topo. As duas são
closures, como `translations` já é. `places` devolve **array puro**, não `JsonResource` — o
`InertiaPropContractTest` existe justamente porque `JsonResource::collection` envelopa em
`{"data": …}` e quebra o `.map()` no navegador.

- [ ] **Step 7: Rode os testes**

```bash
./vendor/bin/sail test --filter=CurrentPlaceTest
```

Esperado: PASS em 6 dos 7. O caso
`test_an_explicit_place_id_in_the_url_updates_the_session` continua falhando até a T4.2.

- [ ] **Step 8: Confirme que o contrato de props seguiu íntegro**

```bash
./vendor/bin/sail test --filter="InertiaSharedProps|InertiaPropContract"
```

Esperado: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Services/CurrentPlaceService.php app/Http/Controllers/App/SetCurrentPlaceController.php app/Http/Middleware/HandleInertiaRequests.php routes/web.php tests/Feature/CurrentPlaceTest.php
git commit -m "feat: adiciona local atual persistido em sessao"
```

---

### T4.2 – T4.5: Adoção do local atual nas listas

**Quatro agentes em paralelo**, um por par controller+página. Todos aplicam a **mesma
transformação**; só mudam os arquivos.

| Tarefa | Controller | Página |
|---|---|---|
| **T4.2** | `BookingController` | `bookings/index.tsx` |
| **T4.3** | `AccessCodeController` | `access-codes/index.tsx` |
| **T4.4** | `DeviceController` | `devices/index.tsx` |
| **T4.5** | `IntegrationController` | `integrations/index.tsx` |

**A transformação, passo a passo:**

- [ ] **Step 1: Injete o serviço e troque a resolução do local**

No método `index()`, substitua o bloco que hoje lê `place_id` do request — o padrão atual é
`if ($request->filled('place_id')) { … }` seguido de validação contra `$userPlaceIds` — por:

```php
public function index(Request $request, CurrentPlaceService $currentPlace): Response
{
    $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');
    $placeId = $currentPlace->resolveForRequest($request, Auth::user());
    // ... o resto do método segue igual: o ->when($placeId, ...) não muda.
```

com `use App\Services\CurrentPlaceService;` no topo. **O `whereIn('place_id', $userPlaceIds)`
não sai** — ele é o escopo de segurança; `$placeId` é só recorte de exibição.

Em `DeviceController` (T4.4) há uma diferença: o filtro aceita também a string
`'unassigned'`. Preserve-a — só a resolução do id numérico muda:

```php
$placeIdParam = $request->query('place_id');

if ($placeIdParam === 'unassigned') {
    $placeFilter = 'unassigned';
} else {
    $resolved = $currentPlace->resolveForRequest($request, Auth::user());
    $placeFilter = $resolved === null ? null : (string) $resolved;
}
```

- [ ] **Step 2: Remova o campo de local do `FilterBar` da página**

Na sua página, apague o objeto `{ type: 'place', key: 'place_id', … }` do array `fields` e a
chave `place_id` do objeto `values`. O seletor global passa a ser o único controle de local.

Em `devices/index.tsx` (T4.4), no lugar dele entra a opção de dispositivos sem local, visível
só quando não há local atual:

```tsx
...(currentPlace === null
    ? [{
        type: 'select' as const,
        key: 'place_id',
        label: t('filter_by_place'),
        options: [
            { value: '', label: t('all_places') },
            { value: 'unassigned', label: t('devices_only_unassigned') },
        ],
      }]
    : []),
```

lendo `currentPlace` de `usePage().props`.

- [ ] **Step 3: Escreva o teste da sua lista**

Acrescente ao arquivo de teste da sua área (crie se não houver), trocando `<rota>` e
`<componente>`:

```php
public function test_the_list_uses_the_current_place_from_the_session(): void
{
    $user = User::factory()->create();
    $mine = Place::create(['name' => 'Casa Azul']);
    $other = Place::create(['name' => 'Casa Verde']);

    foreach ([$mine, $other] as $place) {
        PlaceUser::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'label' => $user->name,
        ]);
    }

    $this->actingAs($user)->post('/app/current-place', ['place_id' => $mine->id]);

    $this->actingAs($user)
        ->get('<rota>')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('<componente>')
            ->where('filters.place_id', $mine->id)
        );
}
```

Ajuste o nome da prop conforme o seu controller já devolve hoje (`filters.place_id` em
bookings e access-codes; `filters.place_id` em devices depois da T1.3; `placeId` em
integrations).

- [ ] **Step 4: Rode os testes da sua área**

```bash
./vendor/bin/sail test --filter=<SuaArea>
```

Esperado: PASS.

- [ ] **Step 5: Commit** (apenas os seus dois arquivos + o seu teste)

```bash
git add <controller> <pagina> <teste>
git commit -m "feat: aplica local atual em <sua area>"
```

**T4.2 tem um passo extra:** depois do Step 4, rode
`./vendor/bin/sail test --filter=CurrentPlaceTest`. O caso
`test_an_explicit_place_id_in_the_url_updates_the_session`, deixado falhando pela T4.1, tem
de passar agora. Se não passar, `resolveForRequest` não está sendo chamado no seu controller.

---

### T4.6: Seletor de local na barra superior

**Files:**
- Modify: `resources/js/layouts/app-layout.tsx`
- Create: `resources/js/components/current-place-select.tsx`

Depende só da T4.1 (props compartilhadas) — roda em paralelo com T4.2–T4.5.

- [ ] **Step 1: Escreva o componente**

Criar `resources/js/components/current-place-select.tsx`:

```tsx
import { router } from '@inertiajs/react';

import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import app from '@/routes/app';

const ALL = '__all__';

export interface CurrentPlaceSelectProps {
    places: { id: number; name: string }[];
    currentPlace: { id: number; name: string } | null;
    className?: string;
}

export function CurrentPlaceSelect({ places, currentPlace, className }: CurrentPlaceSelectProps) {
    const { t } = useTranslations();

    if (places.length === 0) {
        return null;
    }

    function change(value: string) {
        router.post(
            app.currentPlace.update.url(),
            { place_id: value === ALL ? '' : value },
            { preserveScroll: true },
        );
    }

    return (
        <Select value={currentPlace ? String(currentPlace.id) : ALL} onValueChange={change}>
            <SelectTrigger className={className} aria-label={t('place_select_label')}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ALL}>{t('place_select_all')}</SelectItem>
                {places.map((place) => (
                    <SelectItem key={place.id} value={String(place.id)}>
                        {place.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
```

Se o helper gerado pelo Wayfinder não se chamar `app.currentPlace.update`, confira o nome
real em `resources/js/routes/app/` depois de rodar `./vendor/bin/sail npm run dev`, que
regenera os helpers a partir das rotas.

- [ ] **Step 2: Ponha o seletor nas duas barras**

Em `app-layout.tsx`, acrescente `currentPlace` e `places` à interface `AppLayoutPageProps` e
à desestruturação de `props`.

Na barra do desktop, **antes** do `<Breadcrumbs>`:

```tsx
<CurrentPlaceSelect places={places} currentPlace={currentPlace} className="h-8 w-[200px]" />
```

Logo abaixo da barra do mobile, uma linha própria:

```tsx
<div className="border-b border-neutral-200 bg-white px-3.5 py-2 lg:hidden">
    <CurrentPlaceSelect places={places} currentPlace={currentPlace} className="h-9 w-full" />
</div>
```

- [ ] **Step 3: Verifique no navegador**

Troque o local no seletor e confira: Reservas, Códigos, Dispositivos e Integrações vêm
filtrados; a seleção sobrevive à navegação; "Todos os locais" volta ao estado sem recorte.
Clique num tile do detalhe do local: o seletor deve passar a exibir aquele local — é a regra
de precedência funcionando.

- [ ] **Step 4: Rode os testes**

```bash
./vendor/bin/sail npm run test:js && ./vendor/bin/sail test --filter=CurrentPlaceTest
```

Esperado: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/current-place-select.tsx resources/js/layouts/app-layout.tsx
git commit -m "feat: adiciona seletor de local atual na barra superior"
```

---

### ⛔ BARREIRA — fim da onda 4

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test && ./vendor/bin/sail npm run test:js
```

---

## ONDA 5 — Tela de Controle

**Um agente.** As duas tarefas são sequenciais (a segunda edita `app-layout.tsx`).

---

### T5.1: Rota e lista de Controle

Cobre F5 do spec.

**Files:**
- Create: `app/Http/Controllers/App/ControlController.php`
- Create: `resources/js/pages/control/index.tsx`
- Modify: `routes/web.php`
- Test: `tests/Feature/ControlTest.php` (criar)

- [ ] **Step 1: Escreva os testes que falham**

Criar `tests/Feature/ControlTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Place;
use App\Models\PlaceUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ControlTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPlaces(int $count = 2): array
    {
        $user = User::factory()->create();
        $places = [];

        foreach (range(1, $count) as $index) {
            $place = Place::create(['name' => "Local {$index}"]);
            PlaceUser::create([
                'place_id' => $place->id,
                'user_id' => $user->id,
                'role' => 'admin',
                'label' => $user->name,
            ]);
            $places[] = $place;
        }

        return [$user, $places];
    }

    public function test_without_a_current_place_it_lists_the_places(): void
    {
        [$user, $places] = $this->userWithPlaces();

        $this->actingAs($user)
            ->get('/app/control')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('control/index')
                ->has('places', count($places))
            );
    }

    /**
     * O retorno prático de existir um local atual: com um selecionado, abrir uma
     * porta é um clique, não três.
     */
    public function test_with_a_current_place_it_renders_that_place_control_panel(): void
    {
        [$user, $places] = $this->userWithPlaces();

        $this->actingAs($user)->post('/app/current-place', ['place_id' => $places[0]->id]);

        $this->actingAs($user)
            ->get('/app/control')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('places/control')
                ->where('place.id', $places[0]->id)
            );
    }

    public function test_it_only_lists_places_the_user_belongs_to(): void
    {
        [$user] = $this->userWithPlaces(1);
        Place::create(['name' => 'De outro']);

        $this->actingAs($user)
            ->get('/app/control')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('places', 1));
    }
}
```

- [ ] **Step 2: Rode os testes e veja falhar**

```bash
./vendor/bin/sail test --filter=ControlTest
```

Esperado: FAIL — 404 em `/app/control`.

- [ ] **Step 3: Escreva o controller**

Criar `app/Http/Controllers/App/ControlController.php`. Com um local selecionado, ele
**delega** ao `PlaceControlController` — nada de duplicar o mapeamento de dispositivos e o
refresh de snapshot Tuya, que são delicados:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Services\CurrentPlaceService;
use App\Services\DashboardService;
use App\Services\Tuya\TuyaIntegrationService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ControlController extends Controller
{
    /**
     * Com um local atual, esta rota é um atalho para o painel daquele local — o
     * mesmo componente de `/app/places/{place}/control`, renderizado pelo mesmo
     * controller. Sem local atual, lista os locais para escolher.
     */
    public function index(
        CurrentPlaceService $currentPlace,
        PlaceControlController $placeControl,
        TuyaIntegrationService $tuya,
        DashboardService $dashboard,
    ): Response {
        $place = $currentPlace->get(Auth::user());

        if ($place !== null) {
            return $placeControl->show($place, $tuya);
        }

        $data = $dashboard->forUser(Auth::user());

        return Inertia::render('control/index', [
            'places' => $data['places']->map(fn (Place $place): array => [
                'id' => $place->id,
                'name' => $place->name,
                'devices_count' => $place->devices_count,
                'online_count' => $data['onlineCountByPlace']->get($place->id, 0),
            ])->values(),
        ]);
    }
}
```

`DashboardService` já produz exatamente esses agregados, escopados ao usuário. Não escreva
consulta nova.

- [ ] **Step 4: Registre a rota**

Em `routes/web.php`, dentro do grupo `auth`/`app.`:

```php
Route::get('/control', [ControlController::class, 'index'])->name('control.index');
```

com `use App\Http\Controllers\App\ControlController;` no topo.

- [ ] **Step 5: Escreva a página da lista**

Criar `resources/js/pages/control/index.tsx`:

```tsx
import { Head, Link } from '@inertiajs/react';

import { EmptyState } from '@/components/empty-state';
import { Page, PageHeader } from '@/components/page';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import places from '@/routes/app/places';

interface ControlPlace {
    id: number;
    name: string;
    devices_count: number;
    online_count: number;
}

interface ControlIndexProps {
    places: ControlPlace[];
    [key: string]: unknown;
}

export default function ControlIndex({ places: items }: ControlIndexProps) {
    const { t } = useTranslations();

    return (
        <AppLayout breadcrumbs={[{ label: t('control_index_title') }]}>
            <Head title={t('control_index_title')} />

            <Page>
                <PageHeader title={t('control_index_title')} />

                {items.length > 0 ? (
                    <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                        {items.map((place) => {
                            const hasOffline = place.devices_count > 0 && place.online_count < place.devices_count;

                            return (
                                <Link
                                    key={place.id}
                                    href={places.control.url({ place: place.id })}
                                    className="flex items-center gap-4 border-b border-neutral-100 px-4.5 py-3.5 no-underline last:border-b-0 hover:bg-neutral-50"
                                >
                                    <span
                                        className={cn('h-1.5 w-1.5 flex-shrink-0 rounded-full', hasOffline ? 'bg-error-500' : 'bg-success-500')}
                                        aria-hidden="true"
                                    />
                                    <span className="flex-1 text-[13.5px] font-semibold text-neutral-900">{place.name}</span>
                                    <span className="text-[12.5px] text-neutral-500">
                                        {t('dashboard_place_online_label', {
                                            online: place.online_count,
                                            total: place.devices_count,
                                        })}
                                    </span>
                                </Link>
                            );
                        })}
                    </div>
                ) : (
                    <EmptyState message={t('dashboard_empty_state')} />
                )}
            </Page>
        </AppLayout>
    );
}
```

- [ ] **Step 6: Acrescente o atalho de volta no painel do local**

Em `resources/js/pages/places/control.tsx`, no `PageHeader`, acrescente uma ação que limpa o
local atual pela **mesma rota** do seletor global — não invente mecanismo próprio:

```tsx
actions={
    <Link
        href={app.currentPlace.update.url()}
        method="post"
        data={{ place_id: '' }}
        as="button"
        className="cursor-pointer rounded-md border border-neutral-200 bg-white px-3 py-1.5 text-[12.5px] font-semibold text-neutral-700"
    >
        {t('control_all_places')}
    </Link>
}
```

- [ ] **Step 7: Rode os testes**

```bash
./vendor/bin/sail test --filter=ControlTest
```

Esperado: PASS (3 testes).

- [ ] **Step 8: Confirme o caminho das páginas**

```bash
./vendor/bin/sail test --filter=InertiaPagePathTest
```

Esperado: PASS. O diretório novo é `resources/js/pages/control` — **minúsculas**. O CI roda
em sistema de arquivos case-sensitive e não perdoa `Control/`.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/App/ControlController.php resources/js/pages/control/index.tsx resources/js/pages/places/control.tsx routes/web.php tests/Feature/ControlTest.php
git commit -m "feat: adiciona tela de controle com atalho para o local atual"
```

---

### T5.2: "Controle" na sidebar

Último passo do plano. Só agora, porque a rota passou a existir na T5.1.

**Files:**
- Modify: `resources/js/layouts/app-layout.tsx`
- Modify: `resources/js/pages/places/control.tsx`

- [ ] **Step 1: Acrescente o item ao grupo de operação**

Em `app-layout.tsx`, no grupo `nav_group_operation`, **entre** Início e Reservas:

```tsx
{ href: app.control.index.url(), pattern: '/app/control', label: t('nav_control'), icon: <ControlIcon /> },
```

E o ícone, junto dos outros:

```tsx
function ControlIcon() {
    return (
        <svg className={ITEM_ICON_CLASS} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7 L12 12 L15.5 14" />
        </svg>
    );
}
```

- [ ] **Step 2: Mova a trilha do painel do local para a seção Controle**

Em `resources/js/pages/places/control.tsx`, a trilha posta pela T3.1 começava em Locais
porque a seção Controle ainda não existia. Agora ela existe:

```tsx
breadcrumbs={[
    { label: t('nav_control'), href: app.control.index.url() },
    { label: place.name },
]}
```

- [ ] **Step 3: Verifique no navegador**

Com "Todos os locais": clicar em Controle mostra a lista. Com um local selecionado: clicar em
Controle cai direto no painel daquele local, e "Ver todos os locais" volta para a lista. Em
ambos, o item Controle fica aceso na sidebar.

- [ ] **Step 4: Rode a suíte inteira**

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test && ./vendor/bin/sail npm run test:js
```

Esperado: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/layouts/app-layout.tsx resources/js/pages/places/control.tsx
git commit -m "feat: promove Controle a item de primeiro nivel na sidebar"
```

---

### ⛔ BARREIRA FINAL

```bash
./vendor/bin/sail pint && ./vendor/bin/sail test && ./vendor/bin/sail npm run test:js
```

Verificação manual de fechamento, contra os onze defeitos da seção 1 do spec:

- [ ] Sidebar tem dois grupos rotulados, sem item de Integrações iCal (#1, #7)
- [ ] "Controle" está na sidebar e leva ao painel em um clique com local selecionado (#2)
- [ ] Os tiles do local navegam, e a contagem de reservas passa de 10 (#3)
- [ ] O local escolhido sobrevive à troca entre Reservas, Códigos e Dispositivos (#4)
- [ ] O breadcrumb do desktop mostra a trilha completa; o mobile mostra a página atual (#5)
- [ ] O PIN de uma reserva leva ao código, e o código leva de volta à reserva (#8)
- [ ] O detalhe do local tem um botão primário e um menu `...` (#9)
- [ ] O rodapé da sidebar mostra nome e e-mail; "Admin" só para super admin (#10)
- [ ] O menu diz "Início"; os dois cabeçalhos de integração dizem "Integrações" (#11)
- [ ] O detalhe do local lista suas fontes de reserva (spec 2.4)

Fora do escopo, e portanto **não** verificados aqui: área de perfil, migração da Tuya para a
área de conta, e o `backHref` de destino fixo (defeito #6) — todos registrados na seção 6 do
spec.
