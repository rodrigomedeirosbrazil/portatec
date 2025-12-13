Perfeito. Vou **organizar, clarear responsabilidades e melhorar a nomenclatura**, mantendo sua ideia original, mas deixando o planejamento mais “arquitetural” e pronto para evoluir.

Vou dividir em **Visão Geral**, **Fluxos principais**, **Arquitetura de comunicação** e **Modelo de entidades** (com sugestões de nomes).

---

# PORTATEC — Planejamento da Plataforma de Controle de Acesso

## 1. Visão Geral

A PORTATEC é uma plataforma de controle de acesso que se comunica em tempo real com dispositivos instalados em **portas e portões**.

O sistema permite:

* Abertura de portas via **PIN de acesso** digitado localmente no dispositivo
* Abertura remota via **comando do servidor**
* Monitoramento de eventos (abertura, fechamento, falha, recusa)
* Integração com plataformas de reservas (Airbnb, Booking.com)

A comunicação entre **servidor ↔ dispositivos** será feita via **WebSocket**, garantindo baixa latência e comunicação em tempo real.

---

## 2. Fluxos Principais

### 2.1 Acesso Local via PIN

1. O dispositivo expõe um **Wi-Fi aberto**
2. O usuário (guest / cleaner) conecta
3. Digita um **PIN de 6 dígitos**
4. O dispositivo valida o PIN com o servidor (ou cache local, no futuro)
5. Se autorizado:

   * A porta/portão é aberto
   * O evento é enviado ao servidor
6. Se recusado:

   * O evento de falha também é enviado ao servidor

---

### 2.2 Acesso Remoto

1. O host acessa o sistema web
2. Seleciona um **Place**
3. Visualiza os **Devices** associados
4. Envia um comando remoto (ex: abrir porta)
5. O servidor envia o comando via WebSocket para o dispositivo
6. O dispositivo executa e retorna o status

---

### 2.3 Integração com Plataformas de Booking

1. O host integra uma **Platform** (Airbnb, Booking.com)
2. O sistema importa reservas via **iCal**
3. Para cada booking:

   * Cria ou associa um PIN de acesso
   * Define período de validade (check-in / check-out)
   * Relaciona o acesso ao Place

---

## 3. Arquitetura de Comunicação

### 3.1 Dispositivos Customizados (Portatec)

* Comunicação direta via **WebSocket**
* Conexão persistente com o servidor
* Recebe comandos em tempo real
* Envia eventos (abertura, fechamento, erro, tentativa inválida)

---

### 3.2 Dispositivos de Terceiros (Tuya)

* Comunicação via **API Tuya**
* O servidor atua como intermediário
* Comportamento abstraído para manter a mesma interface lógica dos dispositivos Portatec

---

## 4. Autenticação e Autorização

* Autenticação baseada em **email e senha**
* Usuários têm diferentes níveis de acesso por **Place**
* O criador de um Place ou Device é o **owner**
* Owners podem compartilhar acesso com outros users (ex: co-host, administrador)

---

## 5. Modelo de Entidades

### 5.1 Users

Representam os **hosts** que utilizam o sistema.

Responsabilidades:

* Autenticação no sistema
* Criar e gerenciar Places
* Criar e gerenciar Devices
* Criar acessos (PINs)
* Integrar plataformas externas

Relacionamentos:

* Um User pode ser owner ou colaborador de vários Places
* Um User pode integrar várias Platforms

---

### 5.2 Places

Representam **locais físicos** (ex: apartamento, casa, condomínio).

Características:

* Contém um ou mais Devices
* Possui uma página de controle
* Permissões baseadas na relação do User com o Place

Funcionalidades:

* Visualização de status dos dispositivos
* Abertura remota
* Gerenciamento de acessos (PINs)
* Associação com bookings

---

### 5.3 Devices

Representam os **dispositivos físicos** de controle de acesso.

Tipos de Device:

* **Pulse**

  * Envia um pulso elétrico para abrir portas ou portões
  * O comportamento pode variar conforme o tipo de instalação
* **Sensor**

  * Envia eventos de mudança de estado (aberto / fechado)

Outras características:

* Associado a um Place
* Possui um **PIN padrão**
* Pode ser:

  * Portatec (WebSocket direto)
  * Tuya (API externa)

---

### 5.4 Accesses (PIN de Acesso)

> 💡 **Sugestão de nome alternativo**:

* `AccessCodes`
* `AccessKeys`
* `EntryCodes`
* `Credentials`

Função:

* Representa um **PIN de 6 dígitos**
* Possui:

  * Data de início
  * Data de fim
* Associado a:

  * Um Place
  * Opcionalmente, um Booking

Comportamento:

* O mesmo PIN é válido para **todos os dispositivos** do Place
* Usado tanto para acesso local quanto validação no servidor

---

### 5.5 Platforms

Representam **plataformas externas de reserva**.

Exemplos:

* Airbnb
* Booking.com

Características:

* Integração via iCal
* Associadas a um User
* Fonte de dados para Bookings

---

### 5.6 Bookings

Representam as **reservas** importadas das plataformas.

Campos principais:

* Data de início (check-in)
* Data de fim (check-out)
* Nome do Guest
* Plataforma de origem
* Place associado

Uso:

* Base para criação automática de PINs
* Controle de validade dos acessos
