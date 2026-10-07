import type { TuyaLockStatus } from '@/types';

export type DeviceId = number;

/** Status da fechadura por dispositivo, indexado pelo id como string. */
export type TuyaLockStatusState = Record<string, TuyaLockStatus>;

export type TuyaLockStatusAction = { type: 'status_received'; deviceId: DeviceId; status: TuyaLockStatus };

export function createInitialLockState(initial: Record<string, TuyaLockStatus> = {}): TuyaLockStatusState {
    return { ...initial };
}

export function tuyaLockStatusReducer(state: TuyaLockStatusState, action: TuyaLockStatusAction): TuyaLockStatusState {
    switch (action.type) {
        case 'status_received':
            return { ...state, [String(action.deviceId)]: action.status };
    }
}

export function getLockStatus(state: TuyaLockStatusState, deviceId: DeviceId): TuyaLockStatus | null {
    return state[String(deviceId)] ?? null;
}
