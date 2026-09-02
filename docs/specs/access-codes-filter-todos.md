# SPEC — Correção do filtro "Todos" na página de Códigos de Acesso (`/app/access-codes`)

## 1. Contexto e problema

Na página de códigos de acesso, o filtro "Todos os locais" não funciona: mesmo
selecionando "Todos os locais", a listagem continua restrita ao primeiro place do
usuário. O mesmo comportamento já foi corrigido na página de reservas
(`/app/bookings`), e este SPEC alinha a página de códigos de acesso ao padrão de lá.

O problema não é de frontend. A `FilterBar` envia corretamente a ausência do filtro
(com `sendEmptyValues=false`, um campo vazio simplesmente não vai na query string). O
defeito está no **fallback** do controller, descrito na seção 3.

## 2. Objetivo

Fazer com que o filtro "Todos os locais" (e, por tabela, "Todos" em status) funcione
na listagem de códigos de acesso, seguindo o mesmo padrão já aplicado em bookings:

- `place_id` é puramente opcional: ausente/vazio = **todos os places** do usuário.
- O escopo de segurança continua sendo `whereIn('place_id', $userPlaceIds)` — nunca o filtro.
- `place_id` de outro usuário é ignorado em silêncio (não vira oráculo de places alheios).
- Valores inválidos de `status` não quebram a tela.

## 3. Diagnóstico técnico (root cause)

`app/Http/Controllers/App/AccessCodeController.php:41-43`:

```php
if ($placeId === null) {
    $placeId = Auth::user()->placeUsers()->value('place_id');
}
```

Sempre que `place_id` está ausente **ou** vazio, este bloco atribui o primeiro place do
usuário. Como o frontend não envia `place_id` quando "Todos os locais" está selecionado,
o controller cai nesse fallback e filtra pelo primeiro place — exatamente o bug que o
`BookingController.php:37-41` documenta como "era ele que impedia 'Todos os locais' de
funcionar".

O filtro de status "Todos" (`value: ''`) já funciona no nível da query (`$status = ''`
não aplica `when`), mas fica mascarado pelo fallback de place descrito acima.

## 4. Escopo

### Incluído

1. Remover o fallback de `place_id` no `AccessCodeController::index`.
2. Tornar a leitura de `place_id` consistente com bookings (`filled` em vez de `has`).
3. Adicionar whitelist de `status` (fallback para "todos" em valores desconhecidos).
4. Habilitar o botão "limpar filtros" (`showClear`) na `FilterBar` da página.
5. Exibir o nome do place na listagem quando não há filtro de place (paridade com bookings).
6. Testes de regressão.

### Fora de escopo

- `display_name` sempre retorna "Código manual" no índice (o índice não faz eager-load de
  `booking`) — será tratado em trabalho separado.
- `sendEmptyValues` na `FilterBar` de access-codes — não é necessário, pois a tela não tem
  "janela padrão" que precise distinguir "sem filtro" de "filtro vazio", como bookings tem.

## 5. Mudanças propostas

### 5.1 Backend — `app/Http/Controllers/App/AccessCodeController.php`

**Remover o fallback e alinhar com bookings.** Substituir:

```php
$placeId = null;
if ($request->has('place_id')) {
    $requestedId = (int) $request->input('place_id');
    if ($userPlaceIds->contains($requestedId)) {
        $placeId = $requestedId;
    }
}

if ($placeId === null) {
    $placeId = Auth::user()->placeUsers()->value('place_id');
}
```

por:

```php
$placeId = null;
if ($request->filled('place_id')) {
    $requestedId = (int) $request->input('place_id');
    if ($userPlaceIds->contains($requestedId)) {
        $placeId = $requestedId;
    }
}
```

**Adicionar whitelist de status.** Junto às constantes da classe:

```php
private const STATUS_OPTIONS = ['', 'active', 'future', 'expired'];
```

E na leitura do filtro:

```php
$status = $request->filled('status') ? $request->string('status')->toString() : '';
if (! in_array($status, self::STATUS_OPTIONS, true)) {
    $status = '';
}
```

**Eager-load de `place`** (necessário para o item 5.3). Na query do índice:

```php
$accessCodes = AccessCode::query()
    ->with('place')
    ->whereIn('place_id', $userPlaceIds)
    ...
```

### 5.2 Backend — `app/Http/Resources/AccessCodeResource.php`

Adicionar `place` ao payload (paridade com `BookingResource::place`):

```php
'place' => new PlaceResource($this->whenLoaded('place')),
```

### 5.3 Frontend — `resources/js/pages/access-codes/index.tsx`

- Trocar `showClear={false}` por `showClear` na `FilterBar`.
- Exibir o nome do place quando não há filtro de place, espelhando bookings
  (`resources/js/pages/bookings/index.tsx:161`):

```tsx
{!filters.place_id ? <p className="m-0 mt-0.5 text-[12.5px] text-neutral-500">{accessCode.place?.name}</p> : null}
```

### 5.4 Frontend — `resources/js/types/models.ts`

Adicionar `place?: Place;` à interface `AccessCode`.

### 5.5 Testes — `tests/Feature/AccessCodes/AccessCodesTest.php`

Adicionar testes de regressão espelhando `tests/Feature/Bookings/BookingsTest.php`:

1. `test_index_with_explicit_empty_place_id_returns_all_places` — usuário com 2 places,
   `GET /app/access-codes?place_id=` retorna códigos dos dois places.
2. `test_index_defaults_to_all_places_when_no_filter` — sem parâmetro nenhum, retorna
   códigos de todos os places (não só o primeiro).
3. `test_index_ignores_place_id_belonging_to_another_user` — `place_id` de outro usuário
   é ignorado; retorna apenas os places próprios (sem fallback indevido).
4. `test_index_with_invalid_status_falls_back_to_all` — `?status=bogus` não quebra e não
   filtra por status.

## 6. Critérios de aceite

- [ ] `GET /app/access-codes` (sem `place_id`) lista códigos de **todos** os places do usuário.
- [ ] Selecionar "Todos os locais" na UI produz o mesmo resultado do item anterior.
- [ ] Selecionar um place específico continua filtrando por aquele place.
- [ ] `?place_id=` (vazio explícito) também lista todos os places.
- [ ] `?place_id=<id de outro usuário>` não vaza dados e não cai no primeiro place.
- [ ] `?status=bogus` não gera erro e mostra todos os status.
- [ ] O nome do place aparece na listagem quando nenhum place está filtrado.
- [ ] O botão "limpar filtros" aparece e restaura o estado inicial.
- [ ] `./vendor/bin/sail test` verde; `./vendor/bin/sail pint` sem alterações.

## 7. Riscos e considerações

- **Impacto no teste existente `test_index_does_not_list_other_users_access_codes`:**
  continua válido — o escopo de segurança é `whereIn('place_id', $userPlaceIds)`, que não muda.
- **Impacto no teste `test_index_filters_by_place`:** continua válido — `?place_id={id}`
  explícito e preenchido segue filtrando.
- **Comportamento de `create`:** não muda. A tela de criação continua usando o primeiro place
  como default (`AccessCodeController::create`), o que é desejado para um formulário.
- **Nada de UI em texto literal:** qualquer novo texto visível deve passar por
  `resources/lang/pt_BR/app.php` (`__('app.<chave>')`). Os rótulos usados ("Todos os locais",
  "Todos", "limpar filtros") já existem; não há strings novas previstas.
