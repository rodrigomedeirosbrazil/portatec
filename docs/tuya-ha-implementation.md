# Implementacao Tuya estilo Home Assistant (Device Sharing)

## Objetivo
Implementar o fluxo de integracao Tuya exatamente como o Home Assistant (HA): uso de User Code, geracao de QR, confirmacao no app Tuya e obtencao de tokens via Device Sharing SDK.

## Referencias tecnicas
- Home Assistant Tuya integration docs (User Code e fluxo) - https://www.home-assistant.io/integrations/tuya/
- HA config flow (gera QR e faz login_result) - https://github.com/home-assistant/core/blob/dev/homeassistant/components/tuya/config_flow.py
- HA init (Manager com endpoint, terminal_id e token_info) - https://github.com/home-assistant/core/blob/dev/homeassistant/components/tuya/__init__.py
- HA constantes (client_id e schema) - https://github.com/home-assistant/core/blob/dev/homeassistant/components/tuya/const.py
- Tuya Smart Life integration repo (usa Device Sharing SDK) - https://github.com/tuya/tuya-smart-life

## Como o HA faz (resumo objetivo)
- Solicita o User Code do app (Tuya ou Smart Life) e nao usa login por usuario/senha.
- Usa LoginControl.qr_code(client_id, schema, user_code) para gerar um token de QR.
- Exibe o QR com payload fixo: tuyaSmart--qrLogin?token=<token>.
- Usa LoginControl.login_result(token, client_id, user_code) ate retornar sucesso.
- Persiste token_info, terminal_id e endpoint retornados.
- Cria um Manager com client_id, user_code, terminal_id, endpoint e token_info.
- O HA usa client_id/schema proprios (HA_3y9q4ak7g4ephrvke e haauthorize).
O fluxo do HA depende do SDK oficial em Python da Tuya, que expõe Manager, DeviceRepository e HomeRepository usados para descobrir e controlar dispositivos.

## Implicacoes para o Portatec
- O fluxo atual via OpenAPI (associated-users) nao eh o mesmo do HA e deve ser substituido.
- O QR que hoje tenta vir da OpenAPI precisa ser gerado a partir do token retornado por LoginControl.
- Alem de access_token/refresh_token, eh necessario armazenar endpoint e terminal_id.

## Requisitos externos (bloqueios possiveis)
- Obter um client_id e schema autorizados para Device Sharing (nao podemos depender do client_id do HA).
- Confirmar com a Tuya se o fluxo de Device Sharing esta habilitado para o app Tuya (Tuya Smart) no seu caso.
- Garantir que o User Code esta acessivel no app (Tuya: Eu > Configuracoes > Conta e Seguranca > Codigo do Usuario).

## Ajustes no produto (funcional)
- Adicionar um campo de entrada para User Code no fluxo de conexao.
- Gerar QR com payload: tuyaSmart--qrLogin?token=<token>.
- Polling de login_result ate sucesso ou expiracao.
- Persistir token_info, endpoint e terminal_id.
- Importar dispositivos via Device Sharing SDK (nao OpenAPI).

## Ajustes no backend (arquitetura)

### Opcao recomendada: sidecar Python com tuya-device-sharing-sdk
Razao: replica o HA com fidelidade e evita reimplementar endpoints internos.

1) Criar um servico Python simples (FastAPI ou Flask) com endpoints internos.
- POST /sharing/qr
  - input: user_code
  - output: { token, expire_time, qr_payload }
- POST /sharing/login-result
  - input: user_code, token
  - output: { ok, token_info, terminal_id, endpoint, uid, expire_time }
- POST /sharing/devices
  - input: token_info, terminal_id, endpoint, user_code
  - output: lista de dispositivos
- POST /sharing/command
  - input: device_id, commands, token_info, terminal_id, endpoint, user_code
  - output: ok
- POST /sharing/unload
  - input: token_info, terminal_id, endpoint, user_code
  - output: ok

2) Laravel chama o sidecar e persiste as credenciais no banco.
3) Os dados persistidos sao usados para futuras chamadas ao sidecar.

### Opcao alternativa: portar o Device Sharing SDK para PHP
Risco maior, porque o SDK esconde detalhes de endpoints e assinatura.

## Mudancas no banco de dados
Atualizar a tabela tuya_accounts (ou criar tuya_sharing_accounts) para guardar:
- user_code (string, encrypted)
- token_info (json, encrypted)
- terminal_id (string)
- endpoint (string)
- expires_at (timestamp)
- active (bool)

Opcional: criar tuya_login_sessions para controlar expiracao de QR e polling.

## Mudancas no codigo (Laravel)

### Config
- Adicionar config para Device Sharing (client_id, schema) em config/tuya.php.
- Remover dependencia de TUYA_CLIENT_SECRET para este fluxo.

### Service
- Criar TuyaSharingService com metodos:
  - createQr(userCode)
  - pollLogin(userCode, token)
  - listDevices(account)
  - sendCommand(account, deviceId, commands)
  - disconnect(account)

### Controller
- /tuya/connect deve virar fluxo em 2 etapas:
  - GET: form de User Code
  - POST: gera QR e exibe pagina de scan
- /tuya/poll/{token}: chama pollLogin e grava conta ao sucesso

### Views
- Ajustar copy para app Tuya (nao Smart Life).
- Exibir input de User Code e QR na mesma tela.

### Importacao de dispositivos
- Mapear o retorno do SDK para tuya_devices.
- Guardar device_id, name, category, online, status.

## Logs e observabilidade
- Logar code/msg do SDK sempre que falhar (para diagnostico).
- Guardar ultimo erro no banco para mostrar na UI se necessario.

## Testes
- Testes unitarios para TuyaSharingService com mocks do sidecar.
- Teste de fluxo completo do connect/poll.
- Teste de importacao de dispositivos.

## Passo a passo de rollout
1) Implementar sidecar e endpoints internos.
2) Criar migracoes e atualizar models.
3) Ajustar UI para User Code + QR.
4) Substituir chamadas atuais por TuyaSharingService.
5) Rodar testes locais.
6) Validacao manual com app Tuya.

## Checklist de validacao
- User Code aceito e QR gerado.
- Scan no app Tuya confirma login.
- Polling retorna sucesso e salva tokens.
- Dispositivos aparecem na lista.
- Comandos funcionam em pelo menos um dispositivo.
