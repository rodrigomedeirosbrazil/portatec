# Design: imagem de produção e deploy

Data: 2026-09-15

Reduz a imagem de produção do portatec e tira do arranque do container o que não
precisa estar lá. O gatilho foi o deploy do `flycomm-server` na mesma VPS: os
dois são Laravel, dividem 1 vCPU e 3.8 GB, e a comparação expôs custos aqui que
não eram visíveis isoladamente.

Isto é infraestrutura e entrega. Não muda domínio, comportamento de aplicação
nem contrato de API. A única mudança de código de aplicação prevista é a da
seção 5, e ela existe para **remover** um passo de build do arranque, não para
mudar o que o app faz.

---

## 1. O que foi medido

Na VPS, em 2026-09-15:

| | portatec | flycomm |
|---|---|---|
| Imagem (`docker image inspect`) | **1.34 GB** | 292 MB |
| RAM | 472 MB (1 container, 7 processos) | 394 MB (7 containers) |
| Deploy (últimos quatro) | ~5 min | ~4 min |
| `.dockerignore` | não existe | existe |

Dentro da imagem publicada, confirmado rodando no container em produção:

- `node_modules`: **306 MB**
- `vendor`: 103 MB
- `.git`: presente
- `certbot` e `npm`: instalados
- `.env`: ausente

A camada de `apt-get` pesa 451 MB e a de `npm ci && npm run build`, 408 MB.

Um aviso sobre medição, porque ele muda a conversa: a coluna `SIZE` do
`docker images` mostra 2.97 GB para esta imagem e 645 MB para a do flycomm. As
duas estão infladas — no overlayfs aquela coluna soma camadas compartilhadas. O
número que vale é o do `docker image inspect`, e é o que está na tabela.

---

## 2. Escopo

Em ordem de execução, do mais barato para o mais invasivo:

1. `.dockerignore` (seção 3)
2. Deleções: `certbot` da imagem, porta 5173, bloco `build:` do compose (seção 4)
3. `VITE_*` em runtime, que remove o Node da imagem e o `npm run build` do
   arranque (seção 5)
4. `mem_limit` e healthcheck (seção 6)
5. Correção do `AGENTS.md`, que descreve um frontend que não existe mais
   (seção 7)

**Fora de escopo, e deliberadamente:** trocar SQLite por Postgres, trocar a base
Debian por Alpine, separar os sete processos do supervisor em containers, e
trocar `docker save`/`scp` por registry. As razões estão na seção 8.

---

## 3. `.dockerignore` — o item que não é sobre tamanho

Não existe nenhum, e o `docker/prod/Dockerfile` faz
`COPY --chown=www-data:www-data . $APP_HOME`. O contexto de build é o
repositório inteiro: 936 MB.

Duas consequências, e a segunda é a que importa:

- **O `.git` inteiro está dentro da imagem publicada.** Não é servido pela web
  — o `root` do nginx interno é `/var/www/public` —, mas todo segredo que já
  passou pelo histórico viaja junto com a imagem, que por sua vez viaja por
  `scp` e fica em disco na VPS.
- **O `.env` não está na imagem hoje, mas por acidente.** O CI faz checkout
  limpo e não tem `.env`; o que protege é a ausência dele no runner, não uma
  regra. Um `docker compose build` na máquina de alguém — que o
  `docker-compose-prod.yml` ainda permite, por ter bloco `build:` — assaria o
  `.env` local numa camada, com as credenciais da Tuya, do banco e do Reverb.

O arquivo exclui pelo menos: `.env` e `.env.*` (com exceção de `.env.example`),
`vendor`, `node_modules`, `.git`, `.github`, `storage`, `tests` e artefatos de
desenvolvimento.

**`storage` inteiro, e não subcaminhos.** É a lição do flycomm: excluir item a
item deixa de fora o próximo diretório que o Laravel inventar ali, e foi
exatamente assim que um `storage/framework/testing/` criado como root quebrou um
build no CI com `error from sender: permission denied`.

**Verificação:** com o `.dockerignore` no lugar, `docker build` e conferir que
`/var/www/.git` e `/var/www/.env` não existem na imagem resultante.

---

## 4. Deleções

**`certbot` e `python3-certbot-nginx` saem do `apt-get` do Dockerfile.** Estão
instalados e nunca são usados: quem emite e renova certificado é o container do
repositório `medeirostec`. Confirmado que o binário existe na imagem em
produção.

**A porta 5173 deixa de ser publicada** no `docker-compose-prod.yml`. É a porta
do servidor de desenvolvimento do Vite, publicada em `0.0.0.0` na VPS. Nada
escuta nela em produção — é porta aberta sem função.

**O bloco `build:` sai do compose de produção.** A imagem chega pronta por
`docker load`; num vCPU compartilhado com outros três sites, o dia em que um
`up -d` decidir compilar é o dia em que todos ficam lentos juntos. Tirar o
`build:` também fecha, de vez, o caminho pelo qual um `.env` local entraria numa
imagem (seção 3).

---

## 5. `VITE_*` em runtime — o item de maior retorno

### O que acontece hoje

O `docker-entrypoint.sh` roda `npm run build` **toda vez que o container sobe**.
No log de produção, o Vite leva 16,57 s, mais o custo do npm em volta, no vCPU
único. O comentário no Dockerfile explica o porquê e está correto: a imagem é
construída no CI sem `.env`, então o bundle sairia com a URL do Reverb
indefinida e o WebSocket não conectaria.

O preço desse arranjo é maior que o passo:

- `node_modules` (306 MB) e o Node precisam existir na **imagem final**, não só
  no build;
- cada deploy e cada `restart` custam ~17 s de indisponibilidade;
- o artefato que foi testado no CI não é o que roda: o bundle é reconstruído no
  servidor, com outra entrada.

### O que muda

O que precisa chegar ao navegador são **quatro valores de conexão**, todos em um
único arquivo de cinco linhas, `resources/js/echo.js`:

```
VITE_REVERB_APP_KEY   VITE_REVERB_HOST   VITE_REVERB_PORT   VITE_REVERB_SCHEME
```

Eles passam a ser entregues em runtime, pelo servidor, em vez de embutidos no
bundle. O frontend é Inertia com React (`resources/views/app.blade.php` carrega
`resources/js/app.tsx`), então há duas formas naturais: props compartilhadas do
Inertia, ou um objeto emitido pelo Blade no documento.

**A spec fixa o requisito, não o mecanismo**, com uma restrição que decide entre
os dois: `resources/js/echo.js` é importado por `app.tsx` no topo, e o `Echo` é
construído no momento do import — antes de o React montar. A solução escolhida
precisa ter os valores disponíveis **nesse instante**, de forma síncrona. Props
do Inertia lidas via `usePage()` dentro de um componente não satisfazem isso sem
adiar a construção do `Echo`; um objeto no documento, emitido pelo Blade antes
do `@vite`, satisfaz.

Com isso:

- `npm run build` sai do entrypoint e acontece uma vez só, no CI;
- `node_modules` e o Node saem da imagem final (o build vira estágio separado, e
  só `public/build` é copiado adiante);
- o bundle que o CI produziu é o bundle que roda.

**Esta é a única parte do escopo que toca código de aplicação e tem risco de
regressão visível ao usuário** — se os valores não chegarem, o status dos
dispositivos em tempo real para de atualizar. Por isso ela vem depois das
outras, e por isso o critério de conclusão dela é funcional, não visual
(seção 9).

---

## 6. Limite de memória e healthcheck

O serviço não tem `mem_limit` nem `healthcheck` no compose de produção. Ele é o
maior consumidor de RAM da VPS (472 MB), e divide a máquina com o mysql do
biolitoral, que não pode morrer. Sem limite, um vazamento aqui faz o OOM killer
escolher a vítima por heurística.

- `mem_limit` no serviço `portatec`, com folga sobre os 472 MB observados.
- `healthcheck` HTTP contra o nginx interno, para `docker ps` parar de dizer
  "Up" quando a aplicação não responde.

O healthcheck cobre o php-fpm e o nginx. **Não cobre os outros cinco processos
do supervisor** — essa limitação é real e fica registrada na seção 8.

---

## 7. `AGENTS.md` desatualizado

A seção 3 do `AGENTS.md` descreve o app do cliente como **Livewire**. O
`resources/views/app.blade.php` carrega `app.tsx` com `@viteReactRefresh`, e
`resources/js/hooks/` usa `useEcho` em componentes React — a interface é Inertia
com React. A seção 9 também não menciona que o entrypoint reconstrói o frontend
no arranque.

`AGENTS.md` é o arquivo que todo agente lê antes de mexer no repositório. Uma
descrição errada do frontend ali custa mais que em qualquer outro documento, e a
seção 5 desta spec torna a seção 9 dele errada de novo se ninguém atualizar.

---

## 8. O que não muda, e por quê

- **SQLite continua.** Sete processos escrevendo no mesmo arquivo, Horizon
  incluído, é um convite a `SQLITE_BUSY` — mas está de pé há meses, o banco tem
  1,1 MB, e trocar por Postgres é migração de dados em produção, não faxina de
  imagem. Fica registrado como observação, não como recomendação.
- **A base Debian continua** (`php:8.4.3-fpm`). Alpine cortaria mais, e é o que
  o flycomm usa, mas aquele projeto tem cinco extensões; aqui há `gd`, `intl`,
  `mqtt` e o conjunto do Horizon, e a troca de libc é onde dependência nativa
  costuma quebrar. As seções 3 a 5 entregam a maior parte do ganho sem esse
  risco. **O pin de patch merece revisão à parte:** fixar `8.4.3` é bom para
  reprodutibilidade e significa que correção de segurança do PHP só entra quando
  alguém trocar o número à mão.
- **Os sete processos continuam num container.** Separá-los daria visibilidade
  de falha por processo — um `mqtt-subscriber` em ciclo de reinício hoje é
  invisível, porque o container segue "Up" —, mas é reescrita de arquitetura, e
  não é o que esta spec se propõe a fazer.
- **`docker save`/`scp`/`load` continua.** Com a imagem em ~500 MB, os cinco
  minutos de deploy caem sozinhos. Registry só compensaria depois disso, e aí
  vale reavaliar.

---

## 9. Critérios de conclusão

- `docker image inspect` mostra a imagem **abaixo de 700 MB** (de 1.34 GB).
  As seções 3 e 4 sozinhas devem levar a ~900 MB; a seção 5 é o que fecha a
  conta. **Esses dois valores são estimativa** — o único número que conta é o
  medido depois.
- `/var/www/.git` e `/var/www/.env` não existem na imagem construída.
- `command -v certbot` e `command -v npm` não encontram nada na imagem final.
- O entrypoint não executa `npm run build`; o log de arranque vai de migrations
  direto ao supervisor.
- **Tempo entre `docker compose up -d` e o primeiro 200 do site cai em pelo
  menos 15 s** em relação ao medido hoje.
- Com o app aberto e um dispositivo mudando de estado, o status atualiza **sem
  recarregar a página** — é isto que prova que a seção 5 não quebrou o
  WebSocket, e nenhum critério visual substitui.
- `docker stats` mostra o container dentro do `mem_limit`, e `docker ps` mostra
  `healthy`.
- `./vendor/bin/sail test` passa.
- Os outros três sites da VPS seguem no ar durante e depois do deploy.

---

## 10. Ordem e risco

As seções 3, 4, 6 e 7 são independentes entre si e reversíveis por `git revert`
— nenhuma muda comportamento de aplicação. A seção 5 muda, e deve ser um
trabalho separado, com teste manual do tempo real antes de virar tag.

Se for preciso parar no meio, parar **depois da seção 4** deixa o repositório
num estado melhor que o atual e sem risco pendente.
