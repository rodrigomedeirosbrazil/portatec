import { describe, expect, it } from 'vitest';

import {
    ACK_TIMEOUT_MS,
    ACKED_DISPLAY_MS,
    MIN_BLOCK_MS,
    commandMatchesPending,
    createInitialState,
    deviceCommandsReducer,
    getControlStatus,
    getResetIdleDelayMs,
    isControlBusy,
    isDeviceAvailable,
    makeControlKey,
    normalizeDeviceStatusValue,
    type DeviceCommandsState,
} from '../device-commands-reducer';

const DEVICE_ID = 42;
const PIN = '1';

function triggerAt(state: DeviceCommandsState, now: number): DeviceCommandsState {
    return deviceCommandsReducer(state, { type: 'command_triggered', deviceId: DEVICE_ID, pin: PIN, now });
}

describe('deviceCommandsReducer', () => {
    it('walks the happy path from idle to acked', () => {
        let state = createInitialState();
        expect(getControlStatus(state, DEVICE_ID, PIN)).toBe('idle');

        state = triggerAt(state, 1_000);
        expect(getControlStatus(state, DEVICE_ID, PIN)).toBe('sending');
        expect(isControlBusy(state, DEVICE_ID, PIN)).toBe(true);

        state = deviceCommandsReducer(state, {
            type: 'command_sent',
            deviceId: DEVICE_ID,
            pin: PIN,
            commandId: 'cmd-1',
        });
        expect(getControlStatus(state, DEVICE_ID, PIN)).toBe('sent');

        // Ack arrives for the pending command.
        expect(commandMatchesPending(state, { deviceId: DEVICE_ID, pin: PIN, commandId: 'cmd-1' })).toBe(true);
        state = deviceCommandsReducer(state, { type: 'ack_matched' });
        expect(getControlStatus(state, DEVICE_ID, PIN)).toBe('acked');

        // ACKED_DISPLAY_MS later, the row returns to idle and pending is cleared.
        state = deviceCommandsReducer(state, {
            type: 'acked_expired',
            deviceId: DEVICE_ID,
            pin: PIN,
            clearsPending: true,
        });
        expect(getControlStatus(state, DEVICE_ID, PIN)).toBe('idle');
        expect(state.pendingCommandId).toBeNull();
        expect(state.pendingDeviceId).toBeNull();
        expect(state.pendingPin).toBeNull();
    });

    it('resets to idle on ack timeout, but only once MIN_BLOCK_MS has passed', () => {
        let state = createInitialState();
        state = triggerAt(state, 0);
        state = deviceCommandsReducer(state, {
            type: 'command_sent',
            deviceId: DEVICE_ID,
            pin: PIN,
            commandId: 'cmd-1',
        });

        // ACK_TIMEOUT_MS (15s) is well past MIN_BLOCK_MS (3s), so the timeout fires clean.
        const now = ACK_TIMEOUT_MS;
        expect(getResetIdleDelayMs(state, now)).toBe(0);

        state = deviceCommandsReducer(state, { type: 'reset_idle' });
        expect(getControlStatus(state, DEVICE_ID, PIN)).toBe('idle');
        expect(state.pendingCommandId).toBeNull();
        expect(state.clickTime).toBeNull();
    });

    it('blocks reset_idle until MIN_BLOCK_MS has elapsed since the click', () => {
        let state = createInitialState();
        state = triggerAt(state, 1_000);
        state = deviceCommandsReducer(state, {
            type: 'command_failed',
            deviceId: DEVICE_ID,
            pin: PIN,
        });

        // command_failed only clears this row's own status...
        expect(getControlStatus(state, DEVICE_ID, PIN)).toBe('idle');
        // ...but the pending click is still tracked, so a too-early reset_idle
        // attempt must be deferred rather than applied.
        const tooEarly = 1_000 + MIN_BLOCK_MS - 1;
        expect(getResetIdleDelayMs(state, tooEarly)).toBe(1);

        const exactlyOnTime = 1_000 + MIN_BLOCK_MS;
        expect(getResetIdleDelayMs(state, exactlyOnTime)).toBe(0);

        state = deviceCommandsReducer(state, { type: 'reset_idle' });
        expect(state.clickTime).toBeNull();
        expect(state.pendingDeviceId).toBeNull();
    });

    it('acks a non-pending (deviceId, pin) pair without disturbing the pending command', () => {
        let state = createInitialState();
        state = triggerAt(state, 0);
        state = deviceCommandsReducer(state, {
            type: 'command_sent',
            deviceId: DEVICE_ID,
            pin: PIN,
            commandId: 'cmd-1',
        });

        const otherDeviceId = 7;
        const otherPin = '2';

        expect(
            commandMatchesPending(state, { deviceId: otherDeviceId, pin: otherPin, commandId: 'cmd-unrelated' }),
        ).toBe(false);

        state = deviceCommandsReducer(state, { type: 'ack_mismatched', deviceId: otherDeviceId, pin: otherPin });

        // The unrelated row is shown as acked...
        expect(getControlStatus(state, otherDeviceId, otherPin)).toBe('acked');
        // ...while the actually-pending command is untouched.
        expect(getControlStatus(state, DEVICE_ID, PIN)).toBe('sent');
        expect(state.pendingCommandId).toBe('cmd-1');

        state = deviceCommandsReducer(state, {
            type: 'acked_expired',
            deviceId: otherDeviceId,
            pin: otherPin,
            clearsPending: false,
        });
        expect(getControlStatus(state, otherDeviceId, otherPin)).toBe('idle');
        // Still untouched: this expiry does not clear pending state.
        expect(state.pendingCommandId).toBe('cmd-1');
    });

    it('ignores a trigger while the row is already busy', () => {
        let state = createInitialState();
        state = triggerAt(state, 0);
        const busyState = state;

        state = triggerAt(state, ACKED_DISPLAY_MS);
        expect(state).toBe(busyState);
    });

    it('tracks device availability independently per device', () => {
        let state = createInitialState();
        expect(isDeviceAvailable(state, DEVICE_ID)).toBe(false);

        state = deviceCommandsReducer(state, { type: 'availability_updated', deviceId: DEVICE_ID, available: true });
        expect(isDeviceAvailable(state, DEVICE_ID)).toBe(true);
        expect(isDeviceAvailable(state, 999)).toBe(false);
    });

    it('normalizes device status values the same way for booleans, ints, strings and unknowns', () => {
        expect(normalizeDeviceStatusValue(true)).toEqual({ kind: 'open' });
        expect(normalizeDeviceStatusValue(1)).toEqual({ kind: 'open' });
        expect(normalizeDeviceStatusValue('1')).toEqual({ kind: 'open' });
        expect(normalizeDeviceStatusValue('open')).toEqual({ kind: 'open' });
        expect(normalizeDeviceStatusValue('on')).toEqual({ kind: 'open' });

        expect(normalizeDeviceStatusValue(false)).toEqual({ kind: 'closed' });
        expect(normalizeDeviceStatusValue(0)).toEqual({ kind: 'closed' });
        expect(normalizeDeviceStatusValue('0')).toEqual({ kind: 'closed' });
        expect(normalizeDeviceStatusValue('closed')).toEqual({ kind: 'closed' });
        expect(normalizeDeviceStatusValue('off')).toEqual({ kind: 'closed' });

        expect(normalizeDeviceStatusValue('unlocked')).toEqual({ kind: 'raw', value: 'unlocked' });
        expect(normalizeDeviceStatusValue(null)).toEqual({ kind: 'raw', value: 'null' });
    });

    it('derives the control key the same way the original Alpine code did', () => {
        expect(makeControlKey(DEVICE_ID, PIN)).toBe(`${DEVICE_ID}-${PIN}`);
    });
});
