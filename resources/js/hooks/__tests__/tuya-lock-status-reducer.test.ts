import { describe, expect, it } from 'vitest';

import type { TuyaLockStatus } from '@/types';

import { createInitialLockState, getLockStatus, tuyaLockStatusReducer } from '../tuya-lock-status-reducer';

const locked: TuyaLockStatus = { locked: true, battery: 51, alert: null, updated_at: null };
const unlocked: TuyaLockStatus = { locked: false, battery: 51, alert: null, updated_at: '2026-10-07T22:00:00+00:00' };

describe('tuyaLockStatusReducer', () => {
    it('starts from the initial status of each device', () => {
        const state = createInitialLockState({ '4': locked });

        expect(getLockStatus(state, 4)).toEqual(locked);
        expect(getLockStatus(state, 5)).toBeNull();
    });

    it('replaces the status of the device in the event', () => {
        const state = tuyaLockStatusReducer(createInitialLockState({ '4': locked, '7': locked }), {
            type: 'status_received',
            deviceId: 4,
            status: unlocked,
        });

        expect(getLockStatus(state, 4)).toEqual(unlocked);
        expect(getLockStatus(state, 7)).toEqual(locked);
    });

    it('stores an event for a device it did not know', () => {
        const state = tuyaLockStatusReducer(createInitialLockState(), {
            type: 'status_received',
            deviceId: 9,
            status: unlocked,
        });

        expect(getLockStatus(state, 9)).toEqual(unlocked);
    });
});
