import { useEffect } from 'react';

interface EchoChannel {
    listen(event: string, callback: (payload: unknown) => void): EchoChannel;
    stopListening(event: string, callback?: (payload: unknown) => void): EchoChannel;
}

interface EchoClient {
    private(channel: string): EchoChannel;
    leaveChannel(channel: string): void;
}

declare global {
    interface Window {
        Echo?: EchoClient;
    }
}

/**
 * Subscribes to a private Echo channel and event, and tears the subscription
 * down on unmount (or whenever `channelName`/`event` change).
 *
 * This replaces the pattern used in the Alpine `x-data` blocks of
 * `resources/views/livewire/places/control.blade.php` and
 * `resources/views/livewire/devices/control.blade.php`, where
 * `window.Echo.private(...)` was subscribed on `init()` and never
 * unsubscribed — every Livewire navigation leaked a listener.
 *
 * `TPayload` types the event payload passed to `handler`.
 *
 * Pass a falsy `channelName` (e.g. while the place/device id is not known
 * yet) to skip subscribing.
 */
export function useEcho<TPayload>(
    channelName: string | number | null | undefined,
    event: string,
    handler: (payload: TPayload) => void,
): void {
    useEffect(() => {
        if (!channelName || typeof window === 'undefined' || !window.Echo) {
            return;
        }

        const name = String(channelName);
        const channel = window.Echo.private(name);
        // Echo's own typings only know about `unknown` payloads; `TPayload` is
        // this hook's caller-declared contract for what the event actually carries.
        const listener = (payload: unknown) => handler(payload as TPayload);

        channel.listen(event, listener);

        return () => {
            channel.stopListening(event, listener);
            window.Echo?.leaveChannel(name);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [channelName, event]);
}
