<section>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <a href="{{ route('app.places.show', $place->id) }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
            <h1 class="m-0 mt-2">Controle – {{ $place->name }}</h1>
        </div>
    </div>

    <div
        class="space-y-4"
        x-data="{
            placeId: {{ (int) $place->id }},
            statusByKey: {},
            deviceAvailable: {},
            pendingCommandId: null,
            pendingDeviceId: null,
            pendingPin: null,
            ackTimeoutId: null,
            clickTime: null,
            MIN_BLOCK_MS: 3000,
            ACK_TIMEOUT_MS: 15000,
            ACKED_DISPLAY_MS: 2000,
            key(deviceId, pin) { return deviceId + '-' + pin; },
            init() {
                const self = this;
                @foreach ($place->devices as $d)
                self.deviceAvailable[{{ $d->id }}] = {{ $d->isAvailable() ? 'true' : 'false' }};
                @endforeach
                if (window.Echo && this.placeId) {
                    window.Echo.private('Place.Device.Command.Ack.' + this.placeId)
                        .listen('.PlaceDeviceCommandAck', (e) => {
                            const cmdId = e.command_id ?? e.commandId;
                            const key = self.key(e.deviceId, String(e.pin));
                            const matches = (e.deviceId == self.pendingDeviceId && String(e.pin) === String(self.pendingPin)) ||
                                (cmdId && cmdId === self.pendingCommandId);
                            if (matches) {
                                self.setAcked(self.pendingDeviceId, self.pendingPin);
                            } else {
                                self.statusByKey[key] = 'acked';
                                setTimeout(() => { self.statusByKey[key] = 'idle'; }, self.ACKED_DISPLAY_MS);
                            }
                        });
                    window.Echo.private('Place.Device.Status.' + this.placeId)
                        .listen('.PlaceDeviceStatus', (e) => {
                            self.deviceAvailable[e.deviceId] = e.isAvailable;
                        });
                }
                window.addEventListener('command-sent', (ev) => {
                    self.pendingCommandId = ev.detail.commandId;
                    self.pendingDeviceId = ev.detail.deviceId;
                    self.pendingPin = ev.detail.pin;
                    self.statusByKey[self.key(ev.detail.deviceId, ev.detail.pin)] = 'sent';
                    if (self.ackTimeoutId) clearTimeout(self.ackTimeoutId);
                    self.ackTimeoutId = setTimeout(() => self.resetIdle(), self.ACK_TIMEOUT_MS);
                });
                window.addEventListener('command-failed', (ev) => {
                    const k = self.key(ev.detail.deviceId, ev.detail.pin);
                    self.statusByKey[k] = 'idle';
                    self.resetIdle();
                });
            },
            status(deviceId, pin) { return this.statusByKey[this.key(deviceId, pin)] || 'idle'; },
            isBusy(deviceId, pin) { return this.status(deviceId, pin) !== 'idle'; },
            setSending(deviceId, pin) {
                const k = this.key(deviceId, String(pin));
                this.statusByKey[k] = 'sending';
                this.pendingDeviceId = deviceId;
                this.pendingPin = pin;
                this.clickTime = Date.now();
            },
            setAcked(deviceId, pin) {
                const k = this.key(deviceId, String(pin));
                this.statusByKey[k] = 'acked';
                if (this.ackTimeoutId) { clearTimeout(this.ackTimeoutId); this.ackTimeoutId = null; }
                const self = this;
                setTimeout(() => {
                    self.statusByKey[k] = 'idle';
                    self.pendingCommandId = null;
                    self.pendingDeviceId = null;
                    self.pendingPin = null;
                }, this.ACKED_DISPLAY_MS);
            },
            resetIdle() {
                const elapsed = this.clickTime ? Date.now() - this.clickTime : 0;
                if (elapsed < this.MIN_BLOCK_MS) {
                    const self = this;
                    setTimeout(() => self.resetIdle(), this.MIN_BLOCK_MS - elapsed);
                    return;
                }
                if (this.ackTimeoutId) { clearTimeout(this.ackTimeoutId); this.ackTimeoutId = null; }
                this.pendingCommandId = null;
                this.pendingDeviceId = null;
                this.pendingPin = null;
                this.clickTime = null;
                const keys = Object.keys(this.statusByKey);
                keys.forEach(k => { this.statusByKey[k] = 'idle'; });
            },
            async triggerCommand(deviceId, action, pin) {
                const k = this.key(deviceId, pin);
                if (this.statusByKey[k] !== 'idle') return;
                this.setSending(deviceId, pin);
                try {
                    await this.$wire.sendCommand(deviceId, action, String(pin));
                } catch (err) {
                    this.statusByKey[k] = 'idle';
                    this.resetIdle();
                }
            }
        }"
    >
        @forelse ($place->devices as $device)
            @php
                $controllableFunctions = $device->deviceFunctions
                    ->filter(fn ($f) => in_array($f->type?->value, ['button', 'switch'], true))
                    ->values();
            @endphp
            <div class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="m-0">{{ $device->name }}</h2>
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="deviceAvailable[{{ $device->id }}] ? 'bg-green-100 text-green-800' : 'bg-neutral-200 text-neutral-700'"
                        x-text="deviceAvailable[{{ $device->id }}] ? 'Online' : 'Offline'"
                    ></span>
                </div>

                @forelse ($controllableFunctions as $function)
                    <div class="mb-2.5 rounded-lg border border-neutral-200 p-3">
                        <p class="m-0 mb-2.5 text-neutral-700">
                            {{ $function->type->label() }} (PIN {{ $function->pin }})
                        </p>

                        @if ($function->type->value === 'button')
                            <button
                                type="button"
                                wire:click="sendCommand({{ $device->id }}, 'push_button', '{{ $function->pin }}')"
                                @click="if (!isBusy({{ $device->id }}, '{{ $function->pin }}')) setSending({{ $device->id }}, '{{ $function->pin }}')"
                                :disabled="isBusy({{ $device->id }}, '{{ $function->pin }}')"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <template x-if="status({{ $device->id }}, '{{ $function->pin }}') === 'idle'">
                                    <span>Acionar</span>
                                </template>
                                <template x-if="status({{ $device->id }}, '{{ $function->pin }}') === 'sending'">
                                    <span class="inline-flex items-center gap-2">
                                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Enviando…
                                    </span>
                                </template>
                                <template x-if="status({{ $device->id }}, '{{ $function->pin }}') === 'sent'">
                                    <span class="inline-flex items-center gap-2">
                                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Aguardando dispositivo…
                                    </span>
                                </template>
                                <template x-if="status({{ $device->id }}, '{{ $function->pin }}') === 'acked'">
                                    <span class="inline-flex items-center gap-2">
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                        </svg>
                                        OK!
                                    </span>
                                </template>
                            </button>
                        @elseif ($function->type->value === 'switch')
                            <button
                                type="button"
                                wire:click="sendCommand({{ $device->id }}, 'toggle', '{{ $function->pin }}')"
                                @click="if (!isBusy({{ $device->id }}, '{{ $function->pin }}')) setSending({{ $device->id }}, '{{ $function->pin }}')"
                                :disabled="isBusy({{ $device->id }}, '{{ $function->pin }}')"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <template x-if="status({{ $device->id }}, '{{ $function->pin }}') === 'idle'">
                                    <span>Alternar</span>
                                </template>
                                <template x-if="status({{ $device->id }}, '{{ $function->pin }}') === 'sending'">
                                    <span class="inline-flex items-center gap-2">
                                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Enviando…
                                    </span>
                                </template>
                                <template x-if="status({{ $device->id }}, '{{ $function->pin }}') === 'sent'">
                                    <span class="inline-flex items-center gap-2">
                                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Aguardando dispositivo…
                                    </span>
                                </template>
                                <template x-if="status({{ $device->id }}, '{{ $function->pin }}') === 'acked'">
                                    <span class="inline-flex items-center gap-2">
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                        </svg>
                                        OK!
                                    </span>
                                </template>
                            </button>
                        @endif
                    </div>
                @empty
                    <p class="m-0 text-neutral-500">Nenhuma função controlável para este dispositivo.</p>
                @endforelse
            </div>
        @empty
            <p class="m-0 rounded-[10px] border border-neutral-300 bg-white p-3.5 text-neutral-500">Nenhum dispositivo neste local.</p>
        @endforelse
    </div>
</section>
