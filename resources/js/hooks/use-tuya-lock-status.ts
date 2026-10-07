import { useCallback, useReducer } from 'react';

import { useEcho } from '@/hooks/use-echo';
import {
    createInitialLockState,
    type DeviceId,
    getLockStatus,
    tuyaLockStatusReducer,
} from '@/hooks/tuya-lock-status-reducer';
import type { TuyaLockStatus } from '@/types';

/** Payload do evento `.PlaceTuyaLockStatus` (`PlaceTuyaLockStatusEvent`). */
interface PlaceTuyaLockStatusPayload {
    deviceId: DeviceId;
    status: TuyaLockStatus;
}

export interface UseTuyaLockStatusOptions {
    /** Local cujo canal `Place.Device.Status.{placeId}` assinar. `null` não assina. */
    placeId: number | null;
    /** Status vindo do carregamento da página, por id do dispositivo. */
    initial: Record<string, TuyaLockStatus>;
}

/** Status das fechaduras Tuya de um local, atualizado pelo Reverb. */
export function useTuyaLockStatus({ placeId, initial }: UseTuyaLockStatusOptions) {
    const [state, dispatch] = useReducer(tuyaLockStatusReducer, initial, createInitialLockState);

    useEcho<PlaceTuyaLockStatusPayload>(placeId ? `Place.Device.Status.${placeId}` : null, '.PlaceTuyaLockStatus', (payload) => {
        dispatch({ type: 'status_received', deviceId: payload.deviceId, status: payload.status });
    });

    const get = useCallback((deviceId: DeviceId) => getLockStatus(state, deviceId), [state]);

    return { get };
}
