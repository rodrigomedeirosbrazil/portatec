import { useCallback, useEffect, useMemo, useRef } from 'react';
import { useReducer } from 'react';

import { useEcho } from '@/hooks/use-echo';
import {
    ACK_TIMEOUT_MS,
    ACKED_DISPLAY_MS,
    type ControlKey,
    type ControlStatus,
    commandMatchesPending,
    createInitialState,
    deviceCommandsReducer,
    getControlStatus,
    getResetIdleDelayMs,
    isControlBusy,
    isDeviceAvailable,
    makeControlKey,
    normalizeDeviceStatusValue,
    type DeviceId,
    type NormalizedDeviceStatus,
} from '@/hooks/device-commands-reducer';

/** Payload of the `.PlaceDeviceCommandAck` broadcast event. */
interface PlaceDeviceCommandAckPayload {
    deviceId: DeviceId;
    pin: string | number;
    command_id?: string | null;
    commandId?: string | null;
}

/** Payload of the `.PlaceDeviceStatus` broadcast event. */
interface PlaceDeviceStatusPayload {
    deviceId: DeviceId;
    isAvailable: boolean;
}

/** Payload of the `.PlaceDeviceFunctionStatus` broadcast event. */
interface PlaceDeviceFunctionStatusPayload {
    deviceId: DeviceId;
    pin: string | number;
    status: unknown;
}

export type DeviceCommandKind = 'toggle' | 'push_button';

export interface DeviceCommandRequest {
    deviceId: DeviceId;
    pin: string;
    action: DeviceCommandKind;
}

export interface DeviceCommandResult {
    commandId: string | null;
}

export interface UseDeviceCommandsOptions {
    /** The place whose realtime channels should be subscribed to. `null`/`undefined` skips subscribing. */
    placeId: DeviceId | null | undefined;
    /** Seeds `functionStatusByKey`, e.g. from `data-initial-function-status` today. */
    initialFunctionStatus?: Record<ControlKey, unknown>;
    /** Seeds `deviceAvailableById`, e.g. from `$device->isAvailable()` today. */
    initialDeviceAvailability?: Record<string, boolean>;
    /**
     * Performs the actual command request (a POST to the Category B route
     * that replaces `Places/Control::sendCommand()` / `Devices/Control::sendCommand()`).
     * Resolving marks the row "sent" and arms the ack timeout; rejecting marks it "idle" again.
     */
    sendCommand: (request: DeviceCommandRequest) => Promise<DeviceCommandResult>;
}

export interface UseDeviceCommandsResult {
    getStatus(deviceId: DeviceId, pin: string): ControlStatus;
    isBusy(deviceId: DeviceId, pin: string): boolean;
    isAvailable(deviceId: DeviceId): boolean;
    /** `null` when no status has ever been received for this function. */
    getFunctionStatus(deviceId: DeviceId, pin: string): NormalizedDeviceStatus | null;
    trigger(deviceId: DeviceId, pin: string, action: DeviceCommandKind): void;
}

/**
 * React binding for the device-control realtime state machine: wires
 * `deviceCommandsReducer` to the three broadcast channels used by
 * `places/control.blade.php` and `devices/control.blade.php`
 * (`Place.Device.Command.Ack.*`, `Place.Device.Status.*`,
 * `Place.Device.Function.Status.*`) plus the ack-timeout / min-block-time /
 * acked-display timers that used to live in Alpine's `x-data`.
 */
export function useDeviceCommands(options: UseDeviceCommandsOptions): UseDeviceCommandsResult {
    const { placeId, sendCommand } = options;

    const [state, dispatch] = useReducer(
        deviceCommandsReducer,
        undefined,
        () =>
            createInitialState({
                functionStatusByKey: options.initialFunctionStatus,
                deviceAvailableById: options.initialDeviceAvailability,
            }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
    );

    const stateRef = useRef(state);
    useEffect(() => {
        stateRef.current = state;
    }, [state]);

    const ackTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const resetIdleTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const ackedExpiryTimeoutsRef = useRef<Map<ControlKey, ReturnType<typeof setTimeout>>>(new Map());

    const clearAckTimeout = useCallback(() => {
        if (ackTimeoutRef.current !== null) {
            clearTimeout(ackTimeoutRef.current);
            ackTimeoutRef.current = null;
        }
    }, []);

    const clearResetIdleTimeout = useCallback(() => {
        if (resetIdleTimeoutRef.current !== null) {
            clearTimeout(resetIdleTimeoutRef.current);
            resetIdleTimeoutRef.current = null;
        }
    }, []);

    // Mirrors `resetIdle()`: re-checks MIN_BLOCK_MS and reschedules itself
    // until enough time has passed since the triggering click, then dispatches.
    const requestResetIdle = useCallback(() => {
        clearResetIdleTimeout();

        const delay = getResetIdleDelayMs(stateRef.current, Date.now());

        if (delay > 0) {
            resetIdleTimeoutRef.current = setTimeout(requestResetIdle, delay);
            return;
        }

        clearAckTimeout();
        dispatch({ type: 'reset_idle' });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [clearAckTimeout, clearResetIdleTimeout]);

    const scheduleAckedExpiry = useCallback((deviceId: DeviceId, pin: string, clearsPending: boolean) => {
        const key = makeControlKey(deviceId, pin);
        const existing = ackedExpiryTimeoutsRef.current.get(key);

        if (existing !== undefined) {
            clearTimeout(existing);
        }

        const timeoutId = setTimeout(() => {
            ackedExpiryTimeoutsRef.current.delete(key);
            dispatch({ type: 'acked_expired', deviceId, pin, clearsPending });
        }, ACKED_DISPLAY_MS);

        ackedExpiryTimeoutsRef.current.set(key, timeoutId);
    }, []);

    useEffect(() => {
        return () => {
            clearAckTimeout();
            clearResetIdleTimeout();
            ackedExpiryTimeoutsRef.current.forEach((timeoutId) => clearTimeout(timeoutId));
            ackedExpiryTimeoutsRef.current.clear();
        };
    }, [clearAckTimeout, clearResetIdleTimeout]);

    useEcho<PlaceDeviceCommandAckPayload>(
        placeId ? `Place.Device.Command.Ack.${placeId}` : null,
        '.PlaceDeviceCommandAck',
        (payload) => {
            const commandId = payload.commandId ?? payload.command_id ?? null;
            const deviceId = payload.deviceId;
            const pin = String(payload.pin);
            const current = stateRef.current;

            if (commandMatchesPending(current, { deviceId, pin, commandId })) {
                const { pendingDeviceId, pendingPin } = current;
                clearAckTimeout();
                dispatch({ type: 'ack_matched' });

                if (pendingDeviceId !== null && pendingPin !== null) {
                    scheduleAckedExpiry(pendingDeviceId, pendingPin, true);
                }

                return;
            }

            dispatch({ type: 'ack_mismatched', deviceId, pin });
            scheduleAckedExpiry(deviceId, pin, false);
        },
    );

    useEcho<PlaceDeviceStatusPayload>(placeId ? `Place.Device.Status.${placeId}` : null, '.PlaceDeviceStatus', (payload) => {
        dispatch({ type: 'availability_updated', deviceId: payload.deviceId, available: payload.isAvailable });
    });

    useEcho<PlaceDeviceFunctionStatusPayload>(
        placeId ? `Place.Device.Function.Status.${placeId}` : null,
        '.PlaceDeviceFunctionStatus',
        (payload) => {
            dispatch({
                type: 'function_status_updated',
                deviceId: payload.deviceId,
                pin: String(payload.pin),
                value: payload.status,
            });
        },
    );

    const trigger = useCallback(
        (deviceId: DeviceId, pin: string, action: DeviceCommandKind) => {
            if (isControlBusy(stateRef.current, deviceId, pin)) {
                return;
            }

            dispatch({ type: 'command_triggered', deviceId, pin, now: Date.now() });

            sendCommand({ deviceId, pin, action })
                .then((result) => {
                    dispatch({ type: 'command_sent', deviceId, pin, commandId: result.commandId });
                    clearAckTimeout();
                    ackTimeoutRef.current = setTimeout(requestResetIdle, ACK_TIMEOUT_MS);
                })
                .catch(() => {
                    dispatch({ type: 'command_failed', deviceId, pin });
                    requestResetIdle();
                });
        },
        [sendCommand, clearAckTimeout, requestResetIdle],
    );

    return useMemo(
        () => ({
            getStatus: (deviceId: DeviceId, pin: string) => getControlStatus(state, deviceId, pin),
            isBusy: (deviceId: DeviceId, pin: string) => isControlBusy(state, deviceId, pin),
            isAvailable: (deviceId: DeviceId) => isDeviceAvailable(state, deviceId),
            getFunctionStatus: (deviceId: DeviceId, pin: string) => {
                const key = makeControlKey(deviceId, pin);

                return key in state.functionStatusByKey ? normalizeDeviceStatusValue(state.functionStatusByKey[key]) : null;
            },
            trigger,
        }),
        [state, trigger],
    );
}
