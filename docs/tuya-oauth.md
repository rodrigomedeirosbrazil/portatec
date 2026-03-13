# Tuya OAuth 2.0 – Configuração e 404

## Se a Tuya retornar 404 na página de autorização

A URL de login/autorização da Tuya (`login.action`) pode ter sido alterada ou descontinuada. Siga estes passos:

### 1. Obter a URL no painel Tuya

1. Acesse [Tuya IoT Platform](https://iot.tuya.com/) (ou [platform.tuya.com](https://platform.tuya.com/)).
2. Abra o seu projeto (ex.: PortaTEC).
3. Vá em **Devices** → **Link App Account** → **Configure OAuth 2.0 Authorization**.
4. Verifique se há alguma URL de “Authorization page”, “H5 page” ou “Authorization link” indicada na tela. Se houver, use essa URL no `.env`.

### 2. Testar URL alternativa no `.env`

Alguns projetos usam um path diferente. No `.env`, defina explicitamente:

```env
# Tente primeiro (path alternativo):
TUYA_OAUTH_AUTHORIZE_URL=https://openapi.tuyaus.com/oauth/authorize
```

Se ainda retornar 404, tente voltar ao path antigo:

```env
TUYA_OAUTH_AUTHORIZE_URL=https://openapi.tuyaus.com/login.action
```

### 3. Região e data center

Confirme que o **data center** do projeto (ex.: Western America) corresponde à região da sua conta no app Tuya/Smart Life (Me → Setting → Account and Security → Region). O domínio da API pode mudar por região (ex.: `openapi.tuyaeu.com` para Europa).

### 4. Suporte Tuya

Se nenhuma URL funcionar:

- [Tuya Developer – OAuth 2.0](https://developer.tuya.com/en/docs/iot/authorization-code-page-usage?id=Kdkyz44dz6a7r)
- [Tuya Service Console](https://service.console.tuya.com/)
- Pergunte qual é a URL atual da “OAuth 2.0 authorization page” / “H5 page” para o fluxo de authorization code.
