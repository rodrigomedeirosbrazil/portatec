# Plano de Refatoração: AccessCode sem `label` e orientado a `booking_id`

## Contexto
Atualmente, `access_codes` já possui `booking_id` (nullable) e relacionamento no model `AccessCode`.
Mesmo assim, o sistema ainda usa o campo `label` como fonte primária de identificação em partes da UI e geração automática.

Objetivo: remover dependência de `label`, usar `booking` como referência principal quando existir, e manter suporte a códigos manuais (`booking_id = null`).

## Estado atual (resumo técnico)
- Tabela `access_codes` contém `booking_id` e `label`.
- `AccessCodeGeneratorService::createForBooking()` preenche `label` com `guest_name`/`Hospede`.
- `AccessCodeGeneratorService::createStandalone()` aceita `label` para códigos manuais.
- Livewire de Access Codes (`Create/Edit/Index`) ainda exibe e edita `label`.
- `BookingObserver` cria/atualiza access code vinculado à reserva.

## Objetivo funcional
- Access code vinculado a reserva: informações de exibição derivadas de `booking`.
- Access code manual: sem reserva vinculada (`booking_id = null`), com identificação visual clara de “manual”.
- Remover campo de entrada/edição de `label` do fluxo do app.
- Opcional/final: remover coluna `label` do banco após migração segura.

## Estratégia recomendada (2 fases)

### Fase 1: Refactor sem migração destrutiva
Objetivo: mudar comportamento da aplicação mantendo compatibilidade com schema atual.

1. Domínio e serviços
- Atualizar `AccessCodeGeneratorService`:
  - `createForBooking()` parar de gravar `label`.
  - `createStandalone()` remover parâmetro `label` e não persistir `label`.
- Ajustar `BookingObserver` para não depender de `label` em updates.

2. Model e apresentação
- Em `AccessCode`, criar um accessor para exibição (ex.: `display_name`) com fallback:
  - Se `booking` existir e tiver `guest_name`: usar nome do hóspede.
  - Se `booking` existir sem `guest_name`: usar texto padrão (ex.: `Reserva #ID`).
  - Se não houver `booking`: usar `Código manual`.
- Atualizar listagens/views para usar esse accessor em vez de `label`.

3. Livewire Access Codes
- `app/Livewire/AccessCodes/Create.php`:
  - Remover propriedade/validação de `label`.
  - Chamar `createStandalone()` sem label.
- `app/Livewire/AccessCodes/Edit.php`:
  - Remover propriedade/validação/persistência de `label`.
- Views:
  - `resources/views/livewire/access-codes/create.blade.php`
  - `resources/views/livewire/access-codes/edit.blade.php`
  - `resources/views/livewire/access-codes/index.blade.php`
  - Remover campo visual de rótulo e ajustar textos.

4. Admin/Filament (se aplicável)
- Revisar resources de AccessCode (se houver) para remover campos/colunas baseados em `label`.

5. Compatibilidade
- Não remover coluna `label` ainda.
- Código deve funcionar mesmo com registros antigos que têm `label` preenchido.

### Fase 2: limpeza de schema
Objetivo: consolidar a mudança de modelo de dados.

1. Criar migration para remover `label` de `access_codes`.
2. Atualizar `fillable` do model `AccessCode` removendo `label`.
3. Garantir que não exista nenhuma referência residual em código/testes.

## Arquivos com maior probabilidade de mudança
- `app/Models/AccessCode.php`
- `app/Services/AccessCode/AccessCodeGeneratorService.php`
- `app/Observers/BookingObserver.php`
- `app/Livewire/AccessCodes/Create.php`
- `app/Livewire/AccessCodes/Edit.php`
- `resources/views/livewire/access-codes/create.blade.php`
- `resources/views/livewire/access-codes/edit.blade.php`
- `resources/views/livewire/access-codes/index.blade.php`
- `database/migrations/*_drop_label_from_access_codes*.php` (fase 2)

## Riscos e cuidados
- Códigos manuais podem perder “identificação textual” se não houver substituto visual.
  - Mitigar com status/etiqueta "Manual" e datas de validade na listagem.
- Relatórios/admin que hoje exibem `label` podem quebrar.
  - Mitigar com busca global por `->label`, `['label']` e colunas de tabela.
- Dados legados com `label` não devem ser necessários após fase 2.
  - Se necessário para auditoria, exportar antes da remoção.

## Plano de testes (checklist)
1. Criar booking -> access code criado com `booking_id` preenchido.
2. Atualizar booking (check-in/out e guest_name) -> exibição do access code atualiza corretamente.
3. Criar access code manual -> `booking_id = null` e UI mostra "Manual".
4. Editar access code manual -> PIN/período atualiza sem erro.
5. Excluir booking -> comportamento esperado para access code vinculado (conforme regra vigente).
6. Listagem de access codes não depende de `label`.
7. Fluxo MQTT/sync continua funcional (sem regressão).

## Sugestão de commits
1. `refactor(access-codes): stop using label in app flows`
2. `chore(db): drop access_codes.label column`
