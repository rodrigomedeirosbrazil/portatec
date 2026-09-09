# Permissão de dispositivo — design

- **Data:** 2026-09-08
- **Status:** aprovado, aguardando plano de implementação

## 1. Problema

O PortaTec hoje tem dois usos: acesso temporário de hóspede (Airbnb) e controle de
acesso de condomínio. A autorização foi desenhada para o primeiro e não sustenta o segundo.

A regra de autorização atual é uma só, repetida em todo o app: *"existe linha em
`place_users` ligando este usuário a este local?"*. O papel (`admin` / `host`) só é
consultado em dois pontos do sistema inteiro — `PlacePolicy::manageMembers()` e
`PlacePolicy::replicate()`. Todo o resto é idêntico para os dois papéis.

Consequências no condomínio:

- Um morador pode desanexar, renomear e reconfigurar o portão do prédio.
- Não existe granularidade por dispositivo: quem está no local vê e aciona tudo que
  está nele.
- Não existe compartilhamento controlado de um dispositivo entre locais de donos
  diferentes. O pivô `device_place` é N:N, mas simétrico: todos os locais têm o mesmo
  poder sobre o dispositivo.
- `PlaceCloneService` **duplica** o dispositivo com `external_device_id = null` em vez
  de compartilhá-lo. Clonar um local não dá acesso ao equipamento — dá uma casca sem
  hardware atrás.

## 2. Cenários que o desenho precisa atender

**Condomínio 1 — sem hóspedes.** O síndico cria o local, cadastra os dispositivos e
adiciona os moradores como membros. Os moradores usam a página de controle. Um morador
pode começar a fazer Airbnb: ele cria o local dele (a casa), leva para lá um dispositivo
do condomínio e adiciona a fechadura dele.

**Condomínio 2 — com hóspedes.** 16 casas, 8 portões de garagem (cada um atende 2
unidades), 2 portões de pedestres (atendem todos), e alguns moradores têm fechadura
eletrônica com PIN. Cada morador é admin do local da própria unidade. Os portões são do
condomínio. Os hóspedes não têm acesso ao sistema — entram só pelo PIN gravado no
equipamento.

## 3. Decisão

A permissão passa a ser **usuário → dispositivo**, não mais derivada do local.

- Quem cria o dispositivo é o **admin** dele.
- O admin **concede uso** a outros usuários.
- Quem recebeu a concessão pode **levar o dispositivo para os locais que administra**.
- Dentro de um local, **todo membro aciona tudo que está lá** — o admin do local decide
  quem entra no local dele.
- **Só o admin do dispositivo altera a configuração** dele.

O local continua sendo onde o dispositivo é usado. A concessão é o que permite trazê-lo
para dentro.

No condomínio 1 ninguém precisa de concessão: os moradores são membros do local e pronto.
No condomínio 2 o síndico cria os 10 portões, concede a garagem A aos moradores 1 e 2 e os
portões de pedestres aos 16, e cada morador monta o local da unidade com o que recebeu mais
a fechadura que ele mesmo criou.

## 4. Modelo de dados

A tabela `device_user` já existe (`device_id`, `user_id`, único no par) e passa a carregar
o vínculo. Ganha uma coluna:

```
device_user.role  enum('admin', 'user')
```

- `admin` — **no máximo um por dispositivo**. É o dono. Dispositivo sem admin é possível
  (ver Backfill) e é um estado inerte: ninguém o edita, concede nem anexa.
- `user` — concessão de uso. Zero ou mais.

Hoje `device_user` só é preenchida na importação Tuya (`TuyaConnectController`). A criação
manual de dispositivo (`DeviceController::store`) passa a gravar a linha `admin`.

O pivô `device_place` continua com o significado atual — "em quais locais este dispositivo
é usado". O que muda é quem pode mexer nele.

`devices.place_id` continua sendo o local primário derivado, mantido **apenas** por anexar
e desanexar: anexar preenche quando está nulo, desanexar reaponta para o primeiro local
restante. `DeviceController::update()` para de escrevê-lo.

### Backfill

Para cada dispositivo existente, na ordem:

1. Se já existe linha em `device_user` (importação Tuya), o usuário da linha mais antiga
   vira `admin`; as demais, se houver, viram `user`.
2. Senão, o membro `admin` mais antigo do local primário do dispositivo (`devices.place_id`,
   com fallback para o primeiro `device_place`).
3. Senão, o dispositivo fica **sem admin**.

Dispositivo sem admin não aparece na lista de anexar de ninguém e só pode ser corrigido
pelo super admin no painel Filament. É o default seguro: nenhum dispositivo órfão vira
acessível por engano.

Se o usuário admin for excluído, a linha some por `cascadeOnDelete` e o dispositivo fica
órfão — mesmo tratamento.

## 5. Autorização

| Ação | Quem pode |
|---|---|
| Editar dispositivo (nome, marca, `external_device_id`, `default_pin`, funções, pinos) | admin do dispositivo |
| Conceder / revogar uso | admin do dispositivo |
| Anexar a um local | admin do dispositivo **ou** quem tem concessão — e ser admin do local de destino |
| Desanexar de um local | admin daquele local, e só daquele local |
| Acionar / ver na tela de controle | qualquer membro de um local que contenha o dispositivo |

### Mudanças concretas

- **`DevicePolicy` é reescrita** sobre `device_user`. O ramo atual que concede acesso a
  qualquer dispositivo Tuya sem local para qualquer usuário que tenha uma integração Tuya
  própria (`DevicePolicy::hasAccess()`) **desaparece** — era uma brecha entre contas.
- **`DeviceController::update()` para de sincronizar locais.** Hoje ele faz
  `$device->places()->sync($placeIds)` com `$placeIds` validado apenas contra os locais do
  próprio usuário: salvar a edição de um dispositivo compartilhado o arranca de todos os
  outros locais, em silêncio. Locais passam a ser gerenciados só pelos endpoints de
  anexar/desanexar.
- **`PlacePolicy::update()` passa a exigir `role = admin`** e cobre renomear o local,
  anexar e desanexar dispositivo. `host` perde as três — hoje tem todas. Ele mantém tudo
  o mais: acionar, criar e editar códigos de acesso, reservas e histórico. Um morador
  `host` continua, portanto, podendo gravar PIN nos dispositivos do local — decisão
  consciente, tomada com a objeção na mesa (um PIN permanente é acesso físico
  transferível, invisível para o admin do local, diferente de apertar o botão no painel).
  `PlaceController::edit()` e `update()` hoje checam vínculo à mão em vez de usar a
  policy; passam a usá-la.
- **A tela de anexar dispositivo passa a listar** os dispositivos que o usuário administra
  mais os que lhe foram concedidos, excluindo os que já estão no local. Hoje
  `PlaceAttachDeviceController::create()` lista **todos os dispositivos sem local do sistema
  inteiro**, de todas as contas, e `store()` aceita qualquer `deviceId` sem checar dono —
  qualquer conta consegue anexar o dispositivo de qualquer outra ao próprio local e acioná-lo.
  Essa é a falha mais grave do sistema atual e este spec a fecha.

## 6. Ciclo de vida da concessão

**Conceder.** O admin do dispositivo escolhe um usuário. O dispositivo passa a aparecer
para ele na lista de anexar.

**Revogar.** Corta na hora, sem aviso:

1. Para cada local onde o usuário é admin e o dispositivo está anexado, desanexa —
   *exceto* se outro admin daquele local também tiver concessão (ou for o admin do
   dispositivo). O acesso do local só cai quando ninguém mais o sustenta.
2. Para cada local desanexado, apaga do equipamento os códigos de acesso originados ali
   (mesmo caminho de `AccessCodeSyncService::syncDeletedAccessCode()`).

Hóspedes com estadia em andamento perdem o acesso àquele dispositivo imediatamente. Foi a
escolha deliberada: o síndico precisa conseguir cortar na hora, que é justamente quando
mais importa.

**Desanexar manualmente** tem o mesmo efeito do passo 2: os PINs daquele local saem do
equipamento.

### Transferir o admin do dispositivo

O admin transfere o dispositivo para outro usuário, pela tela do próprio dispositivo. A
confirmação mostra nome e e-mail de quem vai receber, porque a ação não tem volta pela
interface — depois dela, quem transferiu perde a configuração e só o super admin desfaz.

Isso existe porque **síndico muda**. Condomínio troca de síndico a cada um ou dois anos, e
nesse desenho o síndico é o admin de todos os portões; sem transferência self-service, cada
eleição vira um chamado de suporte. Vale igual para "morador vendeu o apartamento e a
fechadura ficou".

A transferência troca o `role` das linhas de `device_user`: quem recebe vira `admin`, quem
transferiu vira `user` — mantém o uso, perde a configuração. As concessões existentes não
são afetadas.

Transferência em lote ("passe todos os meus dispositivos para X") fica fora: é o caso real
do síndico com 10 portões, mas é também uma ação destrutiva o bastante para não valer o
risco antes de alguém reclamar de transferir um por um.

### Escolher a pessoa

Conceder um dispositivo e adicionar um membro a um local usam o mesmo seletor, e ele passa
a ser **por e-mail exato**: digita-se o e-mail completo, e o sistema confirma ou responde
que não encontrou.

Hoje `PlaceMemberSearchController` faz busca livre por nome ou e-mail a partir de dois
caracteres, sem filtro nenhum: qualquer pessoa que crie uma conta e um local enumera nome e
e-mail de toda a base. Manter esse formato significaria repetir a mesma enumeração na tela
de concessão, que é nova. O e-mail exato fecha as duas de uma vez, e não custa nada ao
síndico — ele já precisa do e-mail de cada morador para o morador existir na plataforma.

## 7. PIN

### Unicidade

Deixa de ser "único por local para sempre" e passa a ser **sem sobreposição de janela no
mesmo dispositivo**. Dois hóspedes podem usar `123456` desde que as janelas não se cruzem.

Ao criar um código para o local P com PIN X e janela `[start, end]`:

1. Alvos = dispositivos de P que passam em `Device::supportsPlaceAccessCodes()`.
2. Para cada alvo D, procura códigos com PIN X em qualquer local de D cuja janela se
   sobreponha: `start_a <= end_b && start_b <= end_a`, com `end = null` valendo infinito.
3. Havendo conflito: na geração automática, sorteia outro PIN; no PIN digitado à mão,
   erro de validação com mensagem traduzida.

Hoje `AccessCodeGeneratorService::generatePin()` checa `place + pin` e ignora o tempo por
completo — restritivo demais num eixo e cego no outro. E `createStandalone()` aceita PIN
manual sem checagem nenhuma.

Código sem `end` (permanente) ocupa aquele PIN indefinidamente naquele dispositivo.

### Colisão herdada no anexo

A regra acima vale na criação. No **anexo** ela não é aplicada: anexar um dispositivo pode
juntar num mesmo equipamento dois códigos preexistentes com PIN igual e janela sobreposta.
O anexo prossegue. Travá-lo puniria o morador por um conflito que ele não criou, e regerar
o código trocaria um PIN que o hóspede já pode ter recebido.

Quando isso acontece, o evento de acesso não tem como saber quem entrou, e o sistema não
chuta: registra sem vínculo (ver abaixo).

### Resolução do evento de acesso

`DeviceCommandService::handleAccessEvent()` hoje resolve o local com
`$device->places()->value('places.id')` — o primeiro que o banco devolver — e casa por
`place_id + pin`. Num dispositivo compartilhado isso atribui o acesso ao morador errado.

Passa a resolver por PIN **e** instante do evento (`device_timestamp`, com fallback para
`now()`):

- Candidatos = códigos com aquele PIN, em qualquer local do dispositivo, cuja janela contém
  o instante do evento.
- Exatamente um candidato → vincula (`access_code_id`).
- Nenhum → `access_code_id = null` (PIN inválido ou expirado).
- Mais de um → `access_code_id = null` e os ids vão para
  `metadata.candidate_access_code_ids`.

## 8. Visibilidade

**Códigos gravados no dispositivo.** O admin do dispositivo vê quantos códigos existem no
equipamento dele, de qual local vieram e a janela de validade de cada um — **sem os
dígitos**. Ele audita e revoga sem ganhar acesso à credencial do hóspede de outra pessoa.
Códigos dos locais que ele próprio administra continuam visíveis por inteiro na tela normal
de códigos de acesso.

**Histórico de acesso (`AccessEvent`).**

- Admin do dispositivo: todos os eventos daquele dispositivo.
- Membro de local: os eventos cujo `access_code_id` pertence a um dos locais dele, mais os
  eventos ambíguos (`access_code_id = null` com um candidato de um local dele).
- Eventos sem candidato nenhum (PIN inválido): só o admin do dispositivo.

`AccessEventPolicy` hoje libera o histórico inteiro para qualquer um com vínculo com o
local — com 16 unidades no mesmo portão de pedestres, cada morador acompanharia a
movimentação de todos os outros.

## 9. O que sai

**Clonar local** é removido: rota, `PlaceCloneController`, `PlaceCloneService`, telas,
testes e chaves de tradução. Ele existia como contorno para a falta de compartilhamento
real. Com a concessão no lugar, o único uso que sobrava — "5 unidades idênticas, provisiono
o hardware depois" — não justifica manter o código.

**A tabela `place_device_functions`** é removida junto, com o model, as relações e a
`DevicePlaceFunctionSyncService`. Ela existia para permitir escolher quais funções de um
dispositivo cada local enxerga, mas nunca chegou a fazer isso: é preenchida automaticamente
com todas as funções × todos os locais, e **nenhuma tela decide nada com ela**.

Ela é lida, porém, em cinco lugares — todos como *fallback para descobrir a que locais o
dispositivo pertence*, para resolver o canal de broadcast:

- `DeviceControlController::show()` (resolução do `placeId` do canal em tempo real)
- `DeviceCommandService`, em quatro pontos: resolução de local no envio de comando, merge
  de locais na difusão de status (dois pontos) e em `handleAccessEvent()`

Todos respondem a uma pergunta que `device_place` e `devices.place_id` já respondem. A
remoção troca esses cinco fallbacks pela relação `places` do dispositivo.

**Passo obrigatório antes do `drop`:** a migração precisa primeiro garantir que todo par
(local, dispositivo) presente em `place_device_functions` exista em `device_place`. As duas
tabelas são escritas juntas hoje, mas nada no banco garante isso, e um dispositivo que só
constasse na tabela antiga perderia o vínculo com o local silenciosamente.

Enquanto ela ficar no banco, vai continuar parecendo que existe granularidade por função
onde não existe.

Essa granularidade fica descartada por decisão, não por falta de tempo: **o local é a
unidade de separação de acesso**. Precisou separar, cria outro local — que é exatamente o
que o condomínio 2 faz com os 16 aptos.

## 10. Testes

Além do teste por comportamento novo, a suíte precisa cobrir explicitamente:

- Anexar dispositivo de outra conta ao próprio local → 403. (Não existe hoje; é a falha
  crítica.)
- Editar dispositivo concedido → 403; acionar o mesmo dispositivo → 200.
- Salvar a edição de um dispositivo compartilhado não altera os locais dos outros.
- `host` não anexa nem desanexa dispositivo; `admin` do local sim.
- Revogação desanexa e apaga os PINs do equipamento; não desanexa se outro admin do local
  sustenta o acesso.
- Geração de PIN recusa sobreposição no mesmo dispositivo vindo de locais diferentes, e
  aceita mesmo PIN com janelas disjuntas.
- PIN manual conflitante → erro de validação.
- Evento de acesso com dois candidatos → `access_code_id` nulo e candidatos em `metadata`.
- Histórico: morador não vê evento de código de outro local.
- Backfill: dispositivo Tuya mantém o importador como admin; dispositivo com local pega o
  admin mais antigo; dispositivo sem local fica sem admin.
- `host` não renomeia o local; `admin` do local sim.
- Transferência: quem recebe vira `admin`, quem transferiu vira `user` e continua acionando.
- Seleção por e-mail exato: e-mail parcial ou nome não encontram ninguém.

## 11. Fora de escopo

Três coisas que apareceram na análise, não têm relação com este desenho e viram tarefas
próprias:

- **`PORTATEC_SUPER_ADMIN_EMAILS` é lido com `env()` fora de `config/`** (`User::hasRole()`).
  Como o entrypoint de produção roda `artisan optimize`, o config fica cacheado, o `.env`
  não é carregado, `env()` devolve `null` e vale só o default hard-coded no código. Ou
  seja: **hoje, em produção, a lista de super admin não é a que está no `.env`**. Conserto
  de dez linhas, e vale fazer logo — independe deste trabalho.
- **`UserPolicy::update()` devolve `true`** para qualquer usuário sobre qualquer usuário.
  Não é explorável hoje, porque só é exercida dentro do Filament, que já é fechado por
  `User::canAccessPanel()`. É uma armadilha para o dia em que algo em `/app` chamar
  `can('update', $user)`.
- **Registro aberto** em `/app/register`, sem verificação de e-mail. Não é conserto, é
  decisão de produto: quem pode criar conta no PortaTec? Fica mais fácil de responder
  depois que a concessão existir.

Uma limitação assumida, que não é dívida:

- **Não existe granularidade por função dentro de um local** — ver §9. É decisão, não
  pendência.
