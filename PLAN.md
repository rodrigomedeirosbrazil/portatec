# PORTATEC — Planejamento da Plataforma de Controle de Acesso (Atualizado)

## 1. Visão Geral

A PORTATEC é uma plataforma de controle de acesso que se comunica em tempo real com dispositivos instalados em **portas e portões**, permitindo acesso local via PIN, controle remoto e integração automática com plataformas de booking.

O sistema foi projetado para funcionar de forma **resiliente**, garantindo que os dispositivos continuem operando **mesmo sem conexão com a internet**.

---

## 2. Fluxos Principais

### 2.1 Acesso Local via PIN (Modo Offline-First)

1. O dispositivo expõe um **Wi-Fi aberto**
2. O usuário (guest / cleaner) conecta
3. Digita um **PIN de 6 dígitos**
4. O dispositivo:

   * Valida o PIN **localmente**
   * Verifica se o PIN ainda está dentro do período de validade
5. Se autorizado:

   * A porta/portão é aberto
   * O evento é registrado localmente
6. Se recusado:

   * Acesso é negado
   * O evento é registrado localmente

📌 **Importante**:

* O dispositivo **não depende do servidor** para validar o PIN
* Todos os **AccessCodes válidos (não expirados)** ficam armazenados localmente
* Se o dispositivo tiver acesso à internet:

  * Ele envia o evento de sucesso ou falha ao servidor

---

### 2.2 Sincronização com o Servidor

* Sempre que houver conexão com a internet:

  * O dispositivo sincroniza:

    * Novos AccessCodes
    * Atualizações
    * Expirações
  * Envia eventos pendentes (tentativas de acesso)

Essa abordagem garante:

* Funcionamento offline
* Consistência eventual
* Rastreabilidade de acessos

---

### 2.3 Acesso Remoto

1. O host acessa o sistema web
2. Seleciona um **Place**
3. Visualiza os **Devices** associados
4. Envia um comando remoto (ex: abrir porta)
5. O servidor envia o comando:

   * Via WebSocket (Portatec)
   * Via API Tuya (Tuya)
6. O dispositivo executa a ação
7. O status é retornado ao servidor

---

### 2.4 Integração com Bookings

1. Um booking é criado (via iCal ou manual)
2. Automaticamente:

   * Um **AccessCode** é criado
   * O período de validade é baseado no check-in e check-out
3. O AccessCode é enviado aos dispositivos do Place
4. Os dispositivos armazenam o código localmente

---

## 3. Arquitetura de Comunicação

### 3.1 Dispositivos Portatec (Customizados)

* Comunicação direta com o servidor via **WebSocket**
* Mantêm:

  * Lista local de AccessCodes válidos
  * Cache de eventos
* Responsáveis por:

  * Validação local de PIN
  * Execução de comandos
  * Envio de eventos quando online

---

### 3.2 Dispositivos Tuya

* Comunicação híbrida:

  * **Servidor → Tuya API** para comandos
  * **Tuya → Portatec (Webhooks)** para eventos

Fluxo:

1. O servidor envia comandos via API Tuya
2. O dispositivo executa a ação
3. A Tuya envia eventos (status, abertura, fechamento)
4. O Portatec recebe esses eventos via webhook e normaliza os dados

📌 Isso permite que:

* Dispositivos Tuya se comportem logicamente como dispositivos Portatec
* O sistema mantenha uma interface única para eventos e ações

---

## 4. Autenticação e Autorização

* Autenticação via **email e senha**
* Controle de acesso baseado em **Place**
* Papéis possíveis:

  * Owner
  * Admin / Co-host
  * Viewer (futuro)

---

## 5. Modelo de Entidades (Atualizado)

### 5.1 Users

Representam os hosts do sistema.

Responsabilidades:

* Criar e gerenciar Places
* Criar e gerenciar Devices
* Integrar Platforms
* Visualizar eventos e acessos

Relacionamentos:

* Um User pode estar associado a vários Places
* Um User pode integrar várias Platforms

---

### 5.2 Places

Representam locais físicos.

Características:

* Contêm um ou mais Devices
* Possuem uma página de controle remoto
* Centralizam:

  * Devices
  * AccessCodes
  * Bookings

---

### 5.3 Devices

Representam os dispositivos físicos.

Tipos funcionais:

* **Pulse**

  * Envia pulso elétrico para abertura
* **Sensor**

  * Envia mudanças de estado (aberto / fechado)

Tipos de integração:

* **Portatec**

  * WebSocket direto
  * Offline-first
* **Tuya**

  * API + Webhooks

Outras características:

* Associado a um Place
* Recebe e armazena AccessCodes
* Possui PIN padrão (fallback / manutenção)

---

### 5.4 AccessCodes

Representam os **PINs de acesso**.

Campos principais:

* PIN de 6 dígitos
* Data de início
* Data de fim
* Place associado
* Booking associado (opcional)

Comportamento:

* Válido para todos os Devices do Place
* Sincronizado com os dispositivos
* Armazenado localmente para validação offline

---

### 5.5 Platforms

Plataformas externas de booking.

Exemplos:

* Airbnb
* Booking.com

Características:

* Integração via iCal
* Associadas a um User
* Fonte de Bookings

---

### 5.6 Bookings

Reservas importadas ou criadas no sistema.

Campos:

* Check-in
* Check-out
* Nome do Guest
* Platform
* Place

Regras:

* Cada booking gera automaticamente um **AccessCode**
* O AccessCode é distribuído para os Devices do Place

---

## 6. Pontos Arquiteturais Importantes

* **Offline-first** nos dispositivos
* **Consistência eventual** entre servidor e devices
* **Interface unificada** para eventos (Portatec + Tuya)
* Separação clara entre:

  * Controle de acesso
  * Integração com plataformas
  * Dispositivos físicos
