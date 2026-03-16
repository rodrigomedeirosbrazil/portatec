from __future__ import annotations

from typing import Any, Dict, List, Optional

from fastapi import FastAPI
from pydantic import BaseModel

from tuya_sharing import LoginControl, Manager

app = FastAPI()


class QrRequest(BaseModel):
    client_id: str
    schema: str
    user_code: str


class LoginResultRequest(BaseModel):
    client_id: str
    user_code: str
    token: str


class DevicesRequest(BaseModel):
    client_id: str
    user_code: str
    token_info: Dict[str, Any]
    terminal_id: str
    endpoint: str


class Command(BaseModel):
    code: str
    value: Any


class CommandRequest(BaseModel):
    client_id: str
    user_code: str
    token_info: Dict[str, Any]
    terminal_id: str
    endpoint: str
    device_id: str
    commands: List[Command]


class UnloadRequest(BaseModel):
    client_id: str
    user_code: str
    token_info: Dict[str, Any]
    terminal_id: str
    endpoint: str


def _manager_from_payload(payload: DevicesRequest | CommandRequest | UnloadRequest) -> Manager:
    return Manager(
        payload.client_id,
        payload.user_code,
        payload.terminal_id,
        payload.endpoint,
        payload.token_info,
        None,
    )


def _device_to_dict(device: Any) -> Dict[str, Any]:
    return {
        "id": getattr(device, "id", None),
        "name": getattr(device, "name", "Unknown"),
        "category": getattr(device, "category", None),
        "online": getattr(device, "online", False),
        "status": getattr(device, "status", None),
    }


@app.post("/sharing/qr")
def sharing_qr(payload: QrRequest) -> Dict[str, Any]:
    login = LoginControl()
    response = login.qr_code(payload.client_id, payload.schema, payload.user_code)
    result = response.get("result", {}) if isinstance(response, dict) else {}
    token = result.get("qrcode") or result.get("token")

    out: Dict[str, Any] = {
        "ok": bool(response.get("success", False)) if isinstance(response, dict) else False,
        "result": {
            "token": token or "",
            "expire_time": result.get("expire_time") or result.get("expireTime") or 300,
        },
        "raw": response,
    }
    return out


@app.post("/sharing/login-result")
def sharing_login_result(payload: LoginResultRequest) -> Dict[str, Any]:
    login = LoginControl()
    ok, info = login.login_result(payload.token, payload.client_id, payload.user_code)
    info = info or {}

    return {
        "ok": bool(ok),
        "result": {
            "ok": bool(ok),
            "uid": info.get("uid", ""),
            "token_info": info,
            "terminal_id": info.get("terminal_id") or info.get("terminalId"),
            "endpoint": info.get("endpoint") or info.get("platform_url"),
            "expire_time": info.get("expire_time") or info.get("expireTime"),
        },
    }


@app.post("/sharing/devices")
def sharing_devices(payload: DevicesRequest) -> Dict[str, Any]:
    manager = _manager_from_payload(payload)
    manager.update_device_cache()
    devices = [_device_to_dict(device) for device in manager.device_map.values()]

    return {
        "ok": True,
        "result": {
            "devices": devices,
        },
    }


@app.post("/sharing/command")
def sharing_command(payload: CommandRequest) -> Dict[str, Any]:
    manager = _manager_from_payload(payload)
    command_list = [{"code": cmd.code, "value": cmd.value} for cmd in payload.commands]
    manager.send_commands(payload.device_id, command_list)

    return {"ok": True}


@app.post("/sharing/unload")
def sharing_unload(payload: UnloadRequest) -> Dict[str, Any]:
    manager = _manager_from_payload(payload)
    manager.unload()

    return {"ok": True}
