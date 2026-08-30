/**
 * Pure state machine for device command control rows.
 *
 * This is a 1:1 port of the Alpine `x-data` state machine embedded in
 * `resources/views/livewire/places/control.blade.php` (and duplicated, with a
 * device-scoped variant, in `resources/views/livewire/devices/control.blade.php`).
 *
 * No React, no Echo, no timers here — those live in `use-device-commands.ts`.
 * Everything in this file is a plain function operating on plain data, so it
 * can be unit tested without a browser or a component tree.
 */

/** Per-row lifecycle: idle -> sending -> sent -> acked -> idle. */
export type ControlStatus = 'idle' | 'sending' | 'sent' | 'acked';

/** Device ids arrive as numbers from Inertia props, but Echo payloads are JSON. */
export type DeviceId = string | number;

/** `${deviceId}-${pin}`, the identity used throughout the original Alpine code. */
export type ControlKey = string;

export const MIN_BLOCK_MS = 3000;
export const ACK_TIMEOUT_MS = 15000;
export const ACKED_DISPLAY_MS = 2000;

export function makeControlKey(deviceId: DeviceId, pin: string): ControlKey {
    return `${deviceId}-${pin}`;
}

export interface DeviceCommandsState {
    statusByKey: Record<ControlKey, ControlStatus>;
    /** Raw (un-normalized) status value last received per function key. */
    functionStatusByKey: Record<ControlKey, unknown>;
    /** Device online/offline, keyed by `String(deviceId)`. */
    deviceAvailableById: Record<string, boolean>;
    pendingCommandId: string | null;
    pendingDeviceId: DeviceId | null;
    pendingPin: string | null;
    /** `Date.now()` at the moment the pending command was triggered. */
    clickTime: number | null;
}

export function createInitialState(seed?: {
    functionStatusByKey?: Record<ControlKey, unknown>;
    deviceAvailableById?: Record<string, boolean>;
}): DeviceCommandsState {
    return {
        statusByKey: {},
        functionStatusByKey: { ...(seed?.functionStatusByKey ?? {}) },
        deviceAvailableById: { ...(seed?.deviceAvailableById ?? {}) },
        pendingCommandId: null,
        pendingDeviceId: null,
        pendingPin: null,
        clickTime: null,
    };
}

export type DeviceCommandsAction =
    /** `Place.Device.Status.{placeId}` -> `PlaceDeviceStatus`. */
    | { type: 'availability_updated'; deviceId: DeviceId; available: boolean }
    /** `Place.Device.Function.Status.{placeId}` -> `PlaceDeviceFunctionStatus`. */
    | { type: 'function_status_updated'; deviceId: DeviceId; pin: string; value: unknown }
    /** User clicked a control button. No-ops unless the row is currently idle. */
    | { type: 'command_triggered'; deviceId: DeviceId; pin: string; now: number }
    /** The command request to the server succeeded (client-side `command-sent`). */
    | { type: 'command_sent'; deviceId: DeviceId; pin: string; commandId: string | null }
    /** The command request to the server failed (client-side `command-failed`). */
    | { type: 'command_failed'; deviceId: DeviceId; pin: string }
    /** Ack matched the pending command (by commandId or by the (deviceId, pin) pair). */
    | { type: 'ack_matched' }
    /** Ack received for a key that is not the pending one. */
    | { type: 'ack_mismatched'; deviceId: DeviceId; pin: string }
    /** `ACKED_DISPLAY_MS` elapsed after an ack was shown. */
    | { type: 'acked_expired'; deviceId: DeviceId; pin: string; clearsPending: boolean }
    /** `resetIdle()` in the original code: called on ack timeout or on command failure. */
    | { type: 'reset_idle' };

export function getControlStatus(state: DeviceCommandsState, deviceId: DeviceId, pin: string): ControlStatus {
    return state.statusByKey[makeControlKey(deviceId, pin)] ?? 'idle';
}

export function isControlBusy(state: DeviceCommandsState, deviceId: DeviceId, pin: string): boolean {
    return getControlStatus(state, deviceId, pin) !== 'idle';
}

export function isDeviceAvailable(state: DeviceCommandsState, deviceId: DeviceId): boolean {
    return state.deviceAvailableById[String(deviceId)] ?? false;
}

/**
 * Mirrors the ack matcher used in `places/control.blade.php`:
 * `(e.deviceId == pendingDeviceId && pin === pendingPin) || (commandId && commandId === pendingCommandId)`.
 */
export function commandMatchesPending(
    state: DeviceCommandsState,
    params: { deviceId: DeviceId; pin: string; commandId?: string | null },
): boolean {
    const pairMatches =
        state.pendingDeviceId !== null &&
        String(params.deviceId) === String(state.pendingDeviceId) &&
        String(params.pin) === String(state.pendingPin);

    const commandIdMatches =
        params.commandId != null && state.pendingCommandId != null && params.commandId === state.pendingCommandId;

    return pairMatches || commandIdMatches;
}

/**
 * How long (in ms) the caller must still wait before `reset_idle` is allowed
 * to take effect, per the `MIN_BLOCK_MS` guard in the original `resetIdle()`.
 * Returns 0 when it is safe to dispatch `reset_idle` right away.
 *
 * Kept separate from the reducer so the *timing* decision (deferred via
 * `setTimeout` in the hook) stays outside of, and independently testable
 * from, the state transition itself.
 */
export function getResetIdleDelayMs(state: DeviceCommandsState, now: number, minBlockMs: number = MIN_BLOCK_MS): number {
    if (state.clickTime === null) {
        return 0;
    }

    const elapsed = now - state.clickTime;

    return elapsed < minBlockMs ? minBlockMs - elapsed : 0;
}

/** Classification of a raw device status value, before translation. */
export type NormalizedDeviceStatus = { kind: 'open' } | { kind: 'closed' } | { kind: 'raw'; value: string };

const OPEN_VALUES: ReadonlyArray<unknown> = [true, 1, '1', 'open', 'on'];
const CLOSED_VALUES: ReadonlyArray<unknown> = [false, 0, '0', 'closed', 'off'];

/**
 * Mirrors `formatStatusValue()`: booleans/ints/strings that mean "open" or
 * "closed" are classified as such (labels are resolved by the caller via
 * `app.device_statuses.*`); anything else is passed through as text.
 */
export function normalizeDeviceStatusValue(value: unknown): NormalizedDeviceStatus {
    if (OPEN_VALUES.includes(value)) {
        return { kind: 'open' };
    }

    if (CLOSED_VALUES.includes(value)) {
        return { kind: 'closed' };
    }

    return { kind: 'raw', value: String(value) };
}

export function deviceCommandsReducer(state: DeviceCommandsState, action: DeviceCommandsAction): DeviceCommandsState {
    switch (action.type) {
        case 'availability_updated': {
            return {
                ...state,
                deviceAvailableById: {
                    ...state.deviceAvailableById,
                    [String(action.deviceId)]: action.available,
                },
            };
        }

        case 'function_status_updated': {
            const key = makeControlKey(action.deviceId, action.pin);

            return {
                ...state,
                functionStatusByKey: {
                    ...state.functionStatusByKey,
                    [key]: action.value,
                },
            };
        }

        case 'command_triggered': {
            if (isControlBusy(state, action.deviceId, action.pin)) {
                return state;
            }

            const key = makeControlKey(action.deviceId, action.pin);

            return {
                ...state,
                statusByKey: { ...state.statusByKey, [key]: 'sending' },
                pendingDeviceId: action.deviceId,
                pendingPin: action.pin,
                clickTime: action.now,
            };
        }

        case 'command_sent': {
            const key = makeControlKey(action.deviceId, action.pin);

            return {
                ...state,
                statusByKey: { ...state.statusByKey, [key]: 'sent' },
                pendingCommandId: action.commandId,
                pendingDeviceId: action.deviceId,
                pendingPin: action.pin,
            };
        }

        case 'command_failed': {
            const key = makeControlKey(action.deviceId, action.pin);

            return {
                ...state,
                statusByKey: { ...state.statusByKey, [key]: 'idle' },
            };
        }

        case 'ack_matched': {
            if (state.pendingDeviceId === null || state.pendingPin === null) {
                return state;
            }

            const key = makeControlKey(state.pendingDeviceId, state.pendingPin);

            return {
                ...state,
                statusByKey: { ...state.statusByKey, [key]: 'acked' },
            };
        }

        case 'ack_mismatched': {
            const key = makeControlKey(action.deviceId, action.pin);

            return {
                ...state,
                statusByKey: { ...state.statusByKey, [key]: 'acked' },
            };
        }

        case 'acked_expired': {
            const key = makeControlKey(action.deviceId, action.pin);
            const nextState: DeviceCommandsState = {
                ...state,
                statusByKey: { ...state.statusByKey, [key]: 'idle' },
            };

            if (!action.clearsPending) {
                return nextState;
            }

            return {
                ...nextState,
                pendingCommandId: null,
                pendingDeviceId: null,
                pendingPin: null,
            };
        }

        case 'reset_idle': {
            const idleStatusByKey = Object.fromEntries(
                Object.keys(state.statusByKey).map((key) => [key, 'idle' as ControlStatus]),
            );

            return {
                ...state,
                statusByKey: idleStatusByKey,
                pendingCommandId: null,
                pendingDeviceId: null,
                pendingPin: null,
                clickTime: null,
            };
        }

        default:
            return state;
    }
}
