# Filtros, ordenação e paginação da página de reservas (`/app/bookings`)

- **Data:** 2026-08-31
- **Escopo:** `/app/bookings` (índice). As demais telas de índice ficam inalteradas.
- **Status:** implementado. Suíte verde (201 testes, 1003 asserções), vitest 18/18, Pint e `tsc` limpos.

## 1. Problema

### 1.1 A opção "Todos" não funciona

`FilterBar.visit()` (`resources/js/components/filter-bar.tsx`) descarta parâmetros vazios ao
montar a query string:

```ts
if (value !== '' && value !== undefined && value !== null) { params[key] = value; }
```

Escolher "Todos" no select de local ou de status navega, portanto, para uma URL **sem** o
parâmetro — indistinguível de um acesso limpo à página. O `BookingController::index()` trata a
ausência como "aplique o padrão":

- **Local:** `if ($placeId === null && ! $request->has('place_id'))` faz fallback para o primeiro
  place do usuário. Como o parâmetro foi removido pelo front, "Todos os locais" sempre volta para
  o primeiro place (em produção, "Portatec Test").
- **Status:** `filled('status')` é falso, então o `elseif` define `'future'`. Pior: **"Todas" é
  inalcançável por construção** — mesmo que o parâmetro chegasse como `status=`, `filled()`
  continuaria falso. O único jeito de obter status vazio hoje é ter outro filtro preenchido.

São dois defeitos somados: o front remove a informação e o back interpreta a ausência como
padrão.

### 1.2 O padrão desejado não existe

Não há opção "em andamento + futuras", e o padrão `future` (`check_in > now`) esconde a reserva
com hóspede na casa naquele momento — a mais relevante da tela.

### 1.3 Demais problemas encontrados

1. **Ordenação inadequada.** `orderBy('check_in')` asc funciona por acidente para futuras, mas
   em "Concluídas" mostra as mais antigas primeiro. Falta tie-breaker (`id`), o que torna a
   paginação instável quando há empate de `check_in`.
2. **Semântica de data é "contido em", não "sobrepõe".** `date_from` filtra `check_in >=` e
   `date_to` filtra `check_out <=`, então uma reserva que atravessa a borda do intervalo
   desaparece.
3. **Datas não validadas.** `date_from=lixo` chega ao `whereDate` e devolve lista vazia sem
   aviso.
4. **Sem botão de limpar filtros** (`showClear={false}`).
5. **Lista vazia é ambígua.** O `EmptyState` sempre oferece "Nova reserva", mesmo quando o que
   houve foi um filtro escondendo tudo.
6. **Contagem de resultados invisível** quando há uma única página, porque o `Pagination` esconde
   o resumo junto com os controles.
7. **`nightsBetween` depende do relógio e do timezone do navegador**, não do servidor.

O índice `(place_id, check_in, check_out)` já existe (`2026_01_01_000800_create_bookings_table`),
portanto a paginação não tem problema de desempenho a resolver.

## 2. Decisões de produto

Definidas com o usuário durante o refinamento:

1. **"Todas" no filtro de status significa literalmente tudo**, sem recorte de data por status.
2. **Estado padrão da página:** status "Todas", todos os locais.
3. **Ordenação padrão** (três grupos, nesta ordem):
   1. **em andamento** (`check_in <= agora <= check_out`) — `check_out` ascendente
   2. **futuras** (`check_in > agora`) — `check_in` ascendente
   3. **concluídas** (`check_out < agora`) — `check_out` descendente
4. **Janela padrão de data:** `date_from = hoje - 7 dias`, para não trazer todo o histórico. A
   janela é **visível** — o campo "Data início" vem preenchido e pode ser apagado. Um recorte
   invisível reintroduziria exatamente a classe de bug que este trabalho corrige. Refinado durante
   a implementação: a janela vale só na visita sem filtro nenhum (ver §3.2).
5. **Escopo restrito a bookings.** A mudança na `FilterBar` é opt-in para não alterar o
   comportamento das outras telas de índice.

## 3. Desenho

### 3.1 Contrato de query string — `FilterBar`

Nova prop opcional `sendEmptyValues?: boolean`, default `false` (preserva o comportamento atual
de access-codes, devices, places e integrações). Com `true`, `visit()` inclui **todas** as chaves
declaradas em `fields`, enviando `''` para as vazias. A página de bookings passa `true`.

A montagem dos parâmetros sai de dentro do componente para um helper puro:

```ts
export function buildFilterParams(
    fields: FilterFieldConfig[],
    values: Record<string, string>,
    sendEmptyValues: boolean,
): Record<string, string>
```

O helper é testável no vitest com `environment: 'node'`, sem adicionar jsdom nem
testing-library.

O backend passa a distinguir três estados por chave:

| Estado | Significado |
|---|---|
| chave ausente | visita limpa — aplica o padrão |
| chave presente e vazia | o usuário escolheu "Todos" — sem filtro |
| chave presente com valor | filtra |

`clearFilters()` mantém o comportamento atual (zera todos os campos), o que agora resulta em
"sem recorte nenhum", inclusive sem a janela de 7 dias. Como `hasActiveFilter` considera
`date_from` preenchido, o botão "Limpar filtros" aparece já na visita limpa, sinalizando que há
um recorte ativo.

### 3.2 `BookingController::index()`

O fallback "primeiro place do usuário" é **removido**. O escopo de segurança continua sendo
`whereIn('place_id', $userPlaceIds)` — é ele que protege os dados. `place_id` passa a ser filtro
puramente opcional; um id que não pertença ao usuário é ignorado em silêncio (sem 403, para não
transformar o filtro em oráculo de existência de places alheios).

| Parâmetro | Ausente | Presente e vazio | Com valor |
|---|---|---|---|
| `place_id` | todos os locais | todos os locais | filtra, se pertencer ao usuário |
| `status` | `all` | `all` | whitelist `all\|current\|future\|past`; inválido → `all` |
| `date_from` | `hoje - 7 dias`, **apenas na visita sem nenhuma chave de filtro** | sem filtro | filtra, se for data válida |
| `date_to` | sem filtro | sem filtro | filtra, se for data válida |
| `guest` | sem filtro | sem filtro | `like %termo%`, com `addcslashes` |
| `source` | sem filtro | sem filtro | filtra |

**Semântica de data passa a ser sobreposição:**

- `date_from` → `check_out >= date_from 00:00:00`
- `date_to` → `check_in <= date_to 23:59:59`

Sem isso, a janela padrão de 7 dias excluiria a estadia em curso iniciada antes dela — o pior
resultado possível para o padrão da tela.

**A janela padrão vale só na visita sem filtro.** Decidido durante a implementação, ao descobrir
que a regra original ("chave ausente sempre aplica o padrão") deixava `?guest=Zezinho` preso aos
últimos 7 dias — e buscar hóspede por nome é quase sempre uma busca no histórico. A janela é o
estado inicial da tela, não um recorte implícito sobre uma busca: se a requisição traz qualquer
uma das seis chaves de filtro e não traz `date_from`, não há filtro de data.

Isto não recria o defeito do `hasAny` descrito na seção 1.1. Lá o acoplamento tornava o valor
"Todas" **inalcançável** pela UI; aqui não, porque a `FilterBar` passou a enviar sempre as seis
chaves — todo estado continua expressável, e o desvio só alcança URL montada à mão ou bookmark
antigo.

Datas inválidas são descartadas (tratadas como ausência de filtro), não propagadas para a query.

O prop `filters` devolvido ao Inertia reflete os valores efetivamente aplicados, incluindo o
`date_from` padrão, para que a `FilterBar` renderize a janela visível.

### 3.3 Ordenação — scopes no model `Booking`

Quatro scopes locais:

- `scopeCurrent(Builder $query, CarbonInterface $now)` — `check_in <= now` e `check_out >= now`
- `scopeFuture(Builder $query, CarbonInterface $now)` — `check_in > now`
- `scopePast(Builder $query, CarbonInterface $now)` — `check_out < now`
- `scopeOrderByTimeline(Builder $query, CarbonInterface $now)` — a ordem da decisão 3

`scopeOrderByTimeline` usa `orderByRaw` com `CASE WHEN` e parâmetros bindados:

```php
$query
    ->orderByRaw('CASE WHEN check_out < ? THEN 2 WHEN check_in <= ? THEN 0 ELSE 1 END', [$now, $now])
    ->orderByRaw('CASE WHEN check_out < ? THEN NULL WHEN check_in <= ? THEN check_out ELSE check_in END', [$now, $now])
    ->orderByRaw('CASE WHEN check_out < ? THEN check_out ELSE NULL END DESC', [$now])
    ->orderBy('id');
```

A primeira cláusula separa os três grupos. As duas seguintes ordenam dentro do grupo: para as
linhas fora do grupo em questão a expressão é `NULL` — constante, portanto inócua, já que a
ordenação é lexicográfica e o grupo foi decidido na primeira cláusula. `id` fecha como
tie-breaker determinístico.

Isto é a exceção pragmática à regra "Eloquent/Query Builder em vez de SQL cru" do `AGENTS.md`:
uma ordenação condicional em três níveis não é expressável no query builder sem SQL. A exceção
fica contida num único scope do model, com parâmetros bindados, sintaxe portável entre MySQL e
SQLite, e coberta por teste unitário.

### 3.4 UI — `resources/js/pages/bookings/index.tsx`

- **`BookingResource` ganha o campo `status`** (`'current' | 'future' | 'past'`), derivado no
  servidor a partir de `now()`. O badge e qualquer decisão de apresentação por grupo passam a não
  depender do relógio nem do timezone do navegador.
- **Badge por grupo:** "Em andamento" (variante de sucesso) e "Concluída" (variante neutra),
  usando o `StatusBadge` existente. Sem marcador, com os três grupos na mesma lista ordenada não
  se distingue uma reserva ativa de uma futura. Futuras não recebem badge de status (é o caso
  comum); o badge de origem iCal continua como está.
- **`showClear` passa a `true`.**
- **Lista em coluna única.** O `md:grid-cols-2` foi removido: a lista é cronológica (em
  andamento → futuras → concluídas) e duas colunas fazem a leitura zigzaguear, escondendo
  justamente a ordem que motivou o trabalho.
- **A opção "Todas" do select de status tem `value: 'all'`, não `''`.** É o valor que o backend
  devolve em `filters.status`; com `''` o select não casa com o prop e renderiza vazio. Um teste
  de feature fixa esse contrato dos dois lados. O cálculo de "há filtro ativo" na página trata
  `'all'` como ausência de recorte.
- **Contagem de resultados sempre visível**, renderizada na própria página a partir de
  `bookings.meta.total`. O `Pagination` não é alterado, para não afetar as outras telas.
- **`EmptyState` sensível a filtro:** havendo filtro ativo, mensagem "nenhuma reserva para este
  filtro" e ação "Limpar filtros"; sem filtro, mantém "Nova reserva".

### 3.5 i18n

Novas chaves em `resources/lang/pt_BR/app.php`:

- `booking_status_badge_current` — "Em andamento"
- `booking_status_badge_past` — "Concluída"
- `booking_empty_state_filtered` — mensagem de lista vazia por filtro
- `booking_results_count` — linha de contagem de resultados

As chaves de opção do select (`booking_status_option_*`) já existem e não mudam.

## 4. Testes

### Feature — `tests/Feature/Bookings/BookingsTest.php`

1. Visita limpa: traz reservas de **todos** os locais do usuário; inclui em andamento e futura;
   exclui concluída fora da janela de 7 dias.
1. A janela padrão não se aplica quando a requisição traz outro filtro: `?guest=X` alcança o
   histórico inteiro.
2. **Regressão do bug relatado:** `?place_id=` explícito devolve reservas de todos os locais.
3. **Regressão:** `?status=` explícito inclui concluídas antigas (sem janela de data).
4. Ordem exata dos três grupos: em andamento (`check_out` asc) → futuras (`check_in` asc) →
   concluídas (`check_out` desc).
5. Sobreposição: reserva iniciada antes de `date_from` e terminando depois dele aparece.
6. `date_from` inválido é ignorado (a lista não fica vazia).
7. `status` inválido é tratado como `all`.
8. `place_id` de place alheio é ignorado e não vaza dados.
9. Ajustar `test_index_filters_by_date_range`, que hoje codifica a semântica de "contido em".

### Unit

- `scopeOrderByTimeline`: ordem dos três grupos e estabilidade do tie-breaker.

### Vitest

- `buildFilterParams` nos dois modos (`sendEmptyValues` `true` e `false`), verificando que o modo
  default preserva o comportamento atual.

## 5. Fora de escopo

O mesmo defeito de "Todos" existe em `AccessCodeController::index()`
(`app/Http/Controllers/App/AccessCodeController.php`), onde é **mais grave**: quando `place_id`
não resolve, o código sempre cai no primeiro place, de modo que não há como ver os PINs de todos
os locais. `DeviceController`, `PlaceController` e `IntegrationController` já tratam a ausência
como "sem filtro" e portanto só dependem da `FilterBar` continuar enviando o que envia hoje — o
que o default `sendEmptyValues: false` garante.

Corrigir access-codes ficou explicitamente fora deste escopo, como follow-up.
