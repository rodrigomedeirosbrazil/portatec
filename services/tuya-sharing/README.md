# Tuya Sharing Sidecar

Pequeno servico HTTP que encapsula o `tuya-device-sharing-sdk` para permitir o fluxo estilo Home Assistant.

## Requisitos
- Python 3.10+

## Instalacao
```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

## Execucao
```bash
uvicorn app:app --host 0.0.0.0 --port 8000
```

## Endpoints
- `POST /sharing/qr`
- `POST /sharing/login-result`
- `POST /sharing/devices`
- `POST /sharing/command`
- `POST /sharing/unload`

Os contratos de payload/retorno estao no codigo em `app.py`.
