# Spec — Reorganização da navegação do app do cliente

- **Data:** 2026-09-01
- **Escopo:** `/app/*` (Inertia + React). O painel Filament em `/admin` **não** é afetado.
- **Status:** proposto

---

## 1. Problema

A navegação do app do cliente cresceu por acréscimo. O resultado é uma sidebar plana de
seis itens que mistura operação diária com configuração, telas operacionais sem entrada
no menu, um breadcrumb que não informa localização, e nenhum conceito de "local atual" —
o contexto se perde a cada troca de tela.

Os onze defeitos levantados no diagnóstico:

| # | Defeito | Onde |
|---|---|---|
| 1 | "Integrações" partida em duas, com pesos e nomes assimétricos | [app-layout.tsx:130](../../resources/js/layouts/app-layout.tsx) |
| 2 | "Controle" — a ação operacional central — não tem entrada no menu | sidebar |
| 3 | Detalhe do local é beco sem saída: tiles sem clique, contagem travada em 10 | [places/show.tsx:70](../../resources/js/pages/places/show.tsx), [PlaceController:70](../../app/Http/Controllers/App/PlaceController.php) |
| 4 | Contexto de local se perde entre telas | todos os controllers de listagem |
| 5 | Breadcrumb decorativo: mostra o rótulo do menu, não a localização | [app-layout.tsx:143](../../resources/js/layouts/app-layout.tsx) |
| 6 | Dois mecanismos de "voltar", ambos parciais e de destino fixo | `PageHeader backHref` |
| 7 | Ações de cabeçalho sem regra; "Integrações iCal" duplicada menu + header | índices de Reservas e Dispositivos |
| 8 | Códigos de acesso: link "Detalhes" leva a formulário de edição; reserva↔código não navega | [access-codes/index.tsx:145](../../resources/js/pages/access-codes/index.tsx), [bookings/show.tsx:88](../../resources/js/pages/bookings/show.tsx) |
| 9 | Quatro ações de peso igual no detalhe do local | [places/show.tsx:46](../../resources/js/pages/places/show.tsx) |
| 10 | Não existe área de conta; o usuário logado é invisível | [HandleInertiaRequests:45](../../app/Http/Middleware/HandleInertiaRequests.php) |
| 11 | Rótulo do menu ≠ título da página; "Dashboard" em inglês num menu pt-BR | `resources/lang/pt_BR/app.php` |

---

## 2. Decisões de design

Quatro decisões foram fechadas antes deste spec e governam tudo o que vem abaixo.

### 2.1 Sidebar em dois grupos

A sidebar passa a separar **operação diária** de **configuração**:

```
OPERAÇÃO                            CONFIGURAÇÃO
  Início            /app/dashboard    Locais        /app/places
  Controle          /app/control      Dispositivos  /app/devices
  Reservas          /app/bookings
  Códigos de acesso /app/access-codes

[rodapé] menu do usuário → Admin (super admin) · Sair
```

"Integrações iCal" **sai** da sidebar (ver 2.4). O item "Admin" sai da lista de navegação
e passa para o menu de usuário no rodapé.

### 2.2 Seletor global de local, persistido em sessão

Existe um **local atual**, escolhido num seletor no topo e guardado na sessão. Ele
pré-filtra Reservas, Códigos de acesso, Dispositivos, Integrações e Controle. O filtro
por local de cada tela é removido — passa a haver um controle só.

### 2.3 "Controle" como item de primeiro nível

Nova rota `/app/control`. Com um local selecionado, ela **renderiza direto o painel de
controle daquele local**; com "Todos os locais", renderiza a lista de locais para
escolher. Esse é o retorno prático de existir um local atual: abrir uma porta vira um
clique.

### 2.4 Integrações: simetria por objeto alimentado

iCal e Tuya **não** são duas visões da mesma coisa. Elas dividem a tabela `integrations`
(separadas por `platform.slug`), mas têm escopos e ciclos de vida diferentes:

|  | iCal | Tuya |
|---|---|---|
| O que se conecta | um feed de uma plataforma | uma conta Tuya |
| Escopo | **por local** (N locais via `place_integration`) | **por usuário** — sem local |
| Como se cria | formulário: plataforma + URL + local | login por QR code |
| Quantas existem | muitas (local × plataforma) | uma, talvez duas |
| Manutenção | editar URL, associar/desassociar locais | reconectar token expirado |

Por isso **não** se unificam em abas. A regra passa a ser: *cada integração mora ao lado
do objeto que ela alimenta, com o mesmo peso*.

- iCal sai da sidebar e fica como sub-navegação de **Reservas** (`/app/bookings/integrations`,
  botão no cabeçalho — como já é hoje).
- Tuya permanece como sub-navegação de **Dispositivos** (`/app/devices/integrations`).
- Além disso, o **detalhe do local passa a listar suas fontes de reserva** — hoje o local
  não sabe que elas existem, apesar de `Place::integrations()` já existir.

**Passo futuro registrado, fora deste spec:** a integração Tuya é uma credencial do
*usuário*, não uma propriedade dos dispositivos. Seu lugar natural é a área de conta,
que ainda não existe. Quando existir, ela migra para lá.

---

## 3. Mudanças detalhadas

### F1 — Ganhos imediatos (independentes da estrutura)

#### F1.1 Tiles do detalhe do local viram links

`resources/js/pages/places/show.tsx`

- Tile **Dispositivos** → `/app/devices?place_id={id}`
- Tile **Reservas** → `/app/bookings?place_id={id}`
- Tile **Códigos ativos** → `/app/access-codes?place_id={id}`

O padrão já existe no dashboard ([dashboard.tsx:119](../../resources/js/pages/dashboard.tsx)).
Extrair o tile repetido (hoje duplicado em `dashboard.tsx` e `places/show.tsx`, cinco
ocorrências do mesmo bloco) para `resources/js/components/stat-tile.tsx`, com `href`
opcional — sem `href` renderiza `<div>`, com `href` renderiza `<Link>`.

#### F1.2 Corrigir a contagem de reservas do local

`app/Http/Controllers/App/PlaceController.php` — `show()`

O tile hoje usa `place.bookings.length`, e `show()` carrega `bookings` com `limit(10)`.
A contagem trava em 10. Trocar por `loadCount('bookings')` e passar `bookings_count`
como prop dedicada. O `limit(10)` do `load` permanece — ele alimenta outra necessidade
(F1.3), não a contagem.

#### F1.3 Painel "Fontes de reserva" no detalhe do local

`PlaceController@show` passa a carregar
`integrations` filtrando `platform.slug != 'tuya'`, e `places/show.tsx` ganha um painel
listando plataforma + data da última atualização, com link para
`/app/bookings/integrations/{id}/edit` e um botão "Adicionar fonte" apontando para
`/app/bookings/integrations/create?place_id={id}`.

`IntegrationController@create` passa a aceitar `?place_id=` e pré-selecionar esse local
(hoje ele pré-seleciona `places.first()`).

#### F1.4 Tiles do dashboard viram links; quinto tile

`resources/js/pages/dashboard.tsx`

- Online/Offline → `/app/devices?status=offline` (exige F1.5)
- Reservas ativas → `/app/bookings?status=current`
- Check-ins hoje → `/app/bookings?date_from={hoje}&date_to={hoje}`
- **Novo tile "Códigos ativos"** → `/app/access-codes?status=active`

`activeAccessCodes` já é calculado e enviado por
[DashboardController:46](../../app/Http/Controllers/App/DashboardController.php) e nunca
renderizado. O tile só consome o que já existe.

Com cinco tiles, o grid passa de `lg:grid-cols-4` para `lg:grid-cols-5`
(`grid-cols-2` no mobile permanece).

#### F1.5 Filtro de status na lista de dispositivos

`DeviceController@index` + `devices/index.tsx` ganham um filtro `status`
(`''` = todos, `online`, `offline`), avaliado sobre o mesmo critério que
`Device::is_available` usa. Sem ele o tile "Offline" do dashboard não tem destino.

#### F1.6 Reserva ↔ código de acesso navegam nos dois sentidos

- `bookings/show.tsx`: o PIN deixa de ser texto morto e vira link para
  `/app/access-codes/{id}/edit` quando `booking.access_code` existe.
- `access-codes/index.tsx`: quando `access_code.booking_id` existe, o `display_name`
  vira link para `/app/bookings/{booking_id}`. Exige expor `booking_id` em
  `AccessCodeResource`.

#### F1.7 "Detalhes" → "Editar" nos códigos de acesso

Códigos de acesso não têm tela de detalhe e não precisam de uma — o formulário de edição
já mostra tudo. Renomear os dois links de `access-codes/index.tsx` (o do PIN e o da
direita) para "Editar", usando a chave `app.edit` que já existe.

#### F1.8 Ações do detalhe do local hierarquizadas

`places/show.tsx`: **Controle** continua como botão primário; **Editar**, **Membros** e
**Clonar** vão para um menu `...` construído com `components/ui/dropdown-menu.tsx`, que
já está no projeto. As condições de `abilities` são preservadas item a item.

#### F1.9 Rótulos

`resources/lang/pt_BR/app.php`

| Chave | De | Para |
|---|---|---|
| `nav_dashboard` | `Dashboard` | `Início` |
| `integrations_ical_title` | `Integrações de reservas (iCal)` | `Integrações de reservas` |

Os dois botões de entrada para integrações passam a usar a **mesma** chave `integrations`
("Integrações"), que já existe e já é a usada no cabeçalho de Dispositivos. Dentro de
Reservas, "de reservas" é redundante — e o texto idêntico nos dois lugares é a expressão
visível da simetria decidida em 2.4. Os títulos das páginas seguem qualificados
("Integrações de reservas", "Integrações de dispositivos"), porque lá o breadcrumb ainda
não existia quando a página é aberta em nova aba.

`nav_bookings_integrations` fica sem uso — sai da sidebar em F2 e é substituída por
`integrations` no cabeçalho de Reservas. Remover a chave de `app.php`.

---

### F2 — Sidebar, menu de usuário e integrações

`resources/js/layouts/app-layout.tsx`

#### F2.1 Grupos

Os `items` viram dois grupos, cada um com um rótulo em caixa alta e cor esmaecida:

```ts
const NAV_GROUPS = [
    { label: t('nav_group_operation'), items: [ /* Início, Controle, Reservas, Códigos */ ] },
    { label: t('nav_group_setup'),     items: [ /* Locais, Dispositivos */ ] },
];
```

Novas chaves: `nav_group_operation` ("Operação"), `nav_group_setup` ("Configuração"),
`nav_control` ("Controle").

#### F2.2 "Integrações iCal" sai da sidebar

Some o sexto item. Consequência direta: o item **Reservas** deixa de precisar de
`exclude: '/app/bookings/integrations*'` — sem um item irmão para as integrações, o
padrão `/app/bookings*` acender em `/app/bookings/integrations` passa a ser o
comportamento **correto**: aquela tela é, de fato, uma sub-página de Reservas.

O parâmetro `exclude` de `NavLink`/`isNavLinkActive` **permanece na API e com seus
testes**. Ele é a contrapartida natural de um matcher com curinga e volta a ser
necessário no dia em que um item de menu aninhar sob outro. O que muda é só o layout
deixar de passá-lo.

#### F2.3 Menu de usuário no rodapé da sidebar

Novo `resources/js/components/user-menu.tsx`, ancorado no rodapé da sidebar:
nome + e-mail (já compartilhados por `HandleInertiaRequests`) abrindo um
`dropdown-menu` com **Admin** (só quando `auth.user.is_super_admin`) e **Sair**.

Consequências:

- A barra superior do desktop perde os links "Admin" e "Sair" — sobra só o breadcrumb
  (F3) e o seletor de local (F4).
- A barra superior do mobile perde o ícone de logout — sobra hambúrguer + breadcrumb,
  que é justamente o espaço de que o breadcrumb precisa. Logout no mobile passa a ser
  hambúrguer → rodapé do menu, o mesmo caminho do desktop.
- O bloco de logout condicional `lg:hidden` que hoje existe dentro do `<aside>` é
  substituído por este menu, que aparece nos dois tamanhos.

"Perfil" é o slot reservado para a futura área de conta. **Não** entra agora — não há
tela de perfil para linkar.

---

### F3 — Breadcrumb real

Hoje `AppLayout` monta `Portatec / {crumb}` onde `crumb` é o rótulo do item ativo da
sidebar. Em `/app/places/12/members` ele diz "Locais". Nunca informa localização.

**Abordagem:** `AppLayout` ganha uma prop opcional `breadcrumbs`:

```ts
export interface Crumb { label: string; href?: string }
export interface AppLayoutProps { children: ReactNode; breadcrumbs?: Crumb[] }
```

- Quando a página não passa nada, o layout mantém o comportamento atual (rótulo da seção
  ativa) — adoção incremental, nenhuma tela quebra.
- Quando passa, renderiza a trilha completa, com todos os itens menos o último
  clicáveis.

Descartada a alternativa de derivar a trilha dentro de `PageHeader` via contexto: o
layout renderiza antes dos filhos e leria o contexto vazio no primeiro passe.

**Telas que passam trilha explícita:**

| Rota | Trilha |
|---|---|
| `/app/places/{id}` | Locais / {nome} |
| `/app/places/{id}/edit` | Locais / {nome} / Editar |
| `/app/places/{id}/members` | Locais / {nome} / Membros |
| `/app/places/{id}/clone` | Locais / {nome} / Clonar |
| `/app/places/{id}/devices/attach` | Locais / {nome} / Adicionar dispositivo |
| `/app/places/{id}/control` | Controle / {nome} |
| `/app/devices/{id}` | Dispositivos / {nome} |
| `/app/devices/{id}/edit` | Dispositivos / {nome} / Editar |
| `/app/devices/{id}/control` | Dispositivos / {nome} / Controle |
| `/app/devices/integrations` | Dispositivos / Integrações |
| `/app/devices/integrations/tuya-connect` | Dispositivos / Integrações / Conectar Tuya |
| `/app/bookings/{id}` | Reservas / Reserva #{id} |
| `/app/bookings/create` | Reservas / Nova reserva |
| `/app/bookings/integrations` | Reservas / Integrações |
| `/app/bookings/integrations/create` | Reservas / Integrações / Nova |
| `/app/bookings/integrations/{id}/edit` | Reservas / Integrações / Editar |
| `/app/access-codes/create` | Códigos de acesso / Novo |
| `/app/access-codes/{id}/edit` | Códigos de acesso / Editar |

**Mobile:** a barra superior mostra o **último** item da trilha (a página onde você
está), não o rótulo da seção. Quando há um item pai com `href`, ele vira um chevron de
voltar à esquerda do título.

**`PageHeader backHref` permanece.** Ele resolve um problema diferente do breadcrumb:
voltar um passo, sem sair para o topo da hierarquia. O defeito #6 (destino fixo, não a
origem real da navegação) fica **fora deste spec** — corrigi-lo exige rastrear a origem
da visita, e o breadcrumb já cobre a maior parte da dor.

---

### F4 — Seletor global de local

#### F4.1 Estado

O local atual mora na sessão, sob a chave `current_place_id`. `null` significa
"Todos os locais" e é um valor válido, não um estado de erro.

**Novo** `app/Services/CurrentPlaceService.php`:

- `get(User $user): ?Place` — lê a sessão e **valida o vínculo em `place_users` a cada
  leitura**. Se o usuário perdeu acesso ao local, retorna `null` e limpa a sessão.
- `set(User $user, ?int $placeId): void` — grava, rejeitando id fora dos locais do
  usuário.

A validação a cada leitura não é defensiva por precaução: sem ela, um usuário removido
de um local continuaria vendo dados dele até trocar de seleção. Isso é exatamente a
classe de defeito que `tests/Feature/PlaceUsersIsolationTest.php` existe para impedir.

#### F4.2 Props compartilhadas

`HandleInertiaRequests::share()` passa a expor:

```php
'currentPlace' => fn () => /* {id, name} | null */,
'places'       => fn () => /* [{id, name}] dos locais do usuário, ordenados por nome */,
```

Ambas como closures, seguindo o padrão de `translations`. `places` acrescenta uma
consulta por requisição; o volume é o número de locais de um usuário e é aceitável.
Se virar problema, o caminho é cache por sessão — **não** faz parte deste spec.

#### F4.3 Rota de troca

```php
Route::post('/current-place', SetCurrentPlaceController::class)->name('current-place.update');
```

Grava e faz `redirect()->back()`, preservando a query string da tela atual **menos**
`place_id`.

#### F4.4 Regra de precedência

> O valor efetivo é sempre o da sessão. Um `place_id` explícito e válido na query string
> **atualiza a sessão** antes de filtrar.

Isso mantém funcionando os links diretos que já existem (os do dashboard, os dos tiles
de F1.1) e, mais importante, mantém o seletor **dizendo a verdade** sobre o que está na
tela. A alternativa — a URL vencer só naquela requisição — deixaria o seletor exibindo
um local diferente do que a lista mostra.

#### F4.5 Telas afetadas

Perdem o campo `type: 'place'` do seu `FilterBar` e passam a ler o local da sessão:

- `BookingController@index`
- `AccessCodeController@index`
- `DeviceController@index`
- `IntegrationController@index`

`PlaceController@index` **não** é afetada — é a lista dos próprios locais, filtrá-la pelo
local atual não faz sentido.

#### F4.6 Dispositivos sem local

O seletor global tem dois tipos de valor: "Todos os locais" e um local. Ele **não**
expressa "sem local atribuído", que hoje existe como opção no filtro de Dispositivos
(`includeUnassigned`).

Solução: `devices/index.tsx` mantém, no seu próprio `FilterBar`, uma opção
**"Somente sem local"**, exibida apenas quando o local atual é "Todos os locais" —
com um local selecionado ela é irrelevante e fica oculta. Isso mantém o controle global
simples e resolve o caso onde ele nasce.

#### F4.7 Onde fica

Na barra superior do desktop, à esquerda, antes do breadcrumb. No mobile, uma linha
própria logo abaixo da barra superior — largura total, para caber o nome do local.

---

### F5 — Tela de Controle

#### F5.1 Rota

```php
Route::get('/control', [ControlController::class, 'index'])->name('control.index');
```

#### F5.2 Comportamento

> **Corrigido depois da implementação.** O desenho descrito abaixo — `/app/control`
> renderizando ora a lista, ora o painel do local atual — **foi revertido**. Uma URL com dois
> significados não tem lugar fixo na hierarquia: o breadcrumb do painel aponta para
> `/app/control` como pai, e com a rota polimórfica esse link levava de volta à própria
> página.
>
> O que vale agora:
>
> - **`/app/control` é sempre a lista de locais.** Um significado só, pai honesto do
>   breadcrumb, bookmarkável.
> - **O atalho de um clique vive no `href` do item "Controle" da sidebar**, calculado no
>   layout: aponta para `/app/places/{atual}/control` quando há local atual, e para
>   `/app/control` quando não há. A esperteza fica na navegação, não na rota.
> - **`/app/places/{place}/control` passa a definir o local atual**, aplicando a mesma regra
>   de precedência de F4.4. Sem isso, um favorito antigo mostrava o local 2 enquanto o
>   seletor do topo e a sidebar apontavam para outro — e o item "Controle" chegava a tirar o
>   usuário do local que ele estava vendo.
> - Como consequência, "Controle" acende em duas rotas e "Locais" para de acender em
>   `/app/places/*/control`. Daí `isNavLinkActive` passar a aceitar lista de padrões, e o
>   `exclude` mantido em F2.2 voltar a ter uso real.
>
> O texto original fica abaixo como registro do que foi tentado.

`ControlController@index` consulta o local atual (`CurrentPlaceService`):

- **Com local selecionado:** renderiza `places/control` para aquele local — o mesmo
  componente de `/app/places/{place}/control`, com as mesmas props. A tela ganha um link
  "Ver todos os locais", que faz `POST` em `app.current-place.update` com `place_id`
  vazio — a mesma rota do seletor global (F4.3), não um mecanismo próprio.
- **Com "Todos os locais":** renderiza uma lista nova, `pages/control/index.tsx`, com
  uma linha por local — nome, `online/total` de dispositivos, ponto de status — cada uma
  levando a `/app/places/{place}/control`.

A lista reaproveita `DashboardService`, que já produz exatamente esses agregados
(`places`, `onlineCountByPlace`, `devices_count`) escopados ao usuário. **Não** criar
uma consulta nova.

#### F5.3 O que não muda

`/app/places/{place}/control` continua existindo com a mesma URL — é para onde a lista
aponta e o que o dashboard e o detalhe do local já linkam. `/app/devices/{device}/control`
também fica como está, alcançável pelo detalhe do dispositivo.

---

## 4. Ordem de implementação

Cada fase é entregável e testável sozinha.

| Fase | Conteúdo | Depende de |
|---|---|---|
| **F1** | Ganhos imediatos (F1.1 – F1.9) | — |
| **F2** | Sidebar em grupos, menu de usuário, integrações fora do menu | — |
| **F3** | Breadcrumb real | F2 (a barra superior é reorganizada lá) |
| **F4** | Seletor global de local | F2 |
| **F5** | Tela de Controle | F2, F4 |

F1 e F2 são independentes entre si e podem ir em paralelo.

Cada fase vira seu próprio plano de implementação e seu próprio PR. F1 é a única que
agrupa itens não relacionados entre si — se ficar grande, quebrar por item (F1.1, F1.2, …),
que são independentes.

---

## 5. Testes

Regra do repositório: toda feature nova precisa de teste; todo bugfix precisa de teste de
regressão. Comandos: `./vendor/bin/sail test` e `./vendor/bin/sail npm run test:js`.

### PHP (`tests/Feature`)

| Fase | Teste |
|---|---|
| F1.2 | `bookings_count` do detalhe do local passa de 10 quando o local tem mais de 10 reservas — regressão do `limit(10)` |
| F1.3 | o detalhe do local lista suas integrações iCal e **não** lista a integração Tuya do mesmo usuário |
| F1.3 | `IntegrationController@create` com `?place_id=` pré-seleciona aquele local |
| F1.5 | `?status=offline` na lista de dispositivos devolve só os indisponíveis |
| F1.6 | `AccessCodeResource` expõe `booking_id` |
| F4.1 | `CurrentPlaceService::get()` devolve `null` quando o vínculo em `place_users` foi removido, e limpa a sessão |
| F4.1 | `set()` rejeita um local de outro usuário |
| F4.4 | `place_id` explícito na query string atualiza a sessão |
| F4.5 | com local na sessão e sem `place_id` na URL, cada uma das quatro listas volta filtrada |
| F4.5 | `PlaceController@index` **ignora** o local da sessão |
| F5.2 | `/app/control` renderiza `control/index` com ou sem local atual |
| F5.2 | abrir `/app/places/{id}/control` torna esse local o atual; um local proibido não vira atual |

`InertiaPropContractTest` ganha casos para as props novas (`currentPlace`, `places`,
`bookings_count`) — coleções como array, nunca envelopadas em `{"data": ...}`.
`InertiaSharedPropsTest` cobre `currentPlace` e `places` no `share()`.

Toda tela nova precisa de entrada compatível com `InertiaPagePathTest`: o diretório real
é `resources/js/pages` em minúsculas, e o CI roda em sistema de arquivos
case-sensitive.

### JS (`vitest`)

| Fase | Teste |
|---|---|
| F2.2 | `isNavLinkActive('/app/bookings/integrations', '/app/bookings*')` é `true` sem `exclude` — o novo comportamento desejado |
| F2.2 | os testes atuais de `exclude` **permanecem**, cobrindo a API que segue existindo |
| F3 | a trilha renderiza todos os itens menos o último como link |
| F3 | sem `breadcrumbs`, o layout cai no rótulo da seção ativa |

---

## 6. Fora de escopo

- **Painel `/admin` (Filament).** Nada aqui o toca.
- **Área de conta / perfil.** O menu de usuário reserva o lugar; a tela não existe e não
  é criada aqui. A migração da integração Tuya para lá depende dela.
- **Defeito #6 — `backHref` de destino fixo.** Corrigir exige rastrear a origem da
  visita. O breadcrumb cobre a maior parte da dor; o resto fica para depois.
- **Tela de detalhe para códigos de acesso.** F1.7 decide que o formulário de edição já
  basta.
- **Barra de abas inferior no mobile.** A sidebar em gaveta continua sendo a navegação
  do mobile.
- **Cache das props compartilhadas.** Só se `places` a cada requisição virar problema
  medido.

---

## 7. Riscos

| Risco | Mitigação |
|---|---|
| O local da sessão vazar dados de local ao qual o usuário perdeu acesso | Validação do vínculo a cada leitura (F4.1), com teste dedicado |
| Remover o filtro por local de quatro telas quebrar links salvos ou externos | A regra de precedência (F4.4) mantém `?place_id=` funcionando em todas elas |
| O redirect de `/app/control` parecer inconsistente (às vezes lista, às vezes painel) | O seletor de local fica visível na mesma barra, explicando o estado; a tela de painel traz "Ver todos os locais" |
| Adoção incremental do breadcrumb deixar telas com trilha e telas sem | O fallback preserva o comportamento atual; a tabela de F3 enumera todas as telas a converter |
