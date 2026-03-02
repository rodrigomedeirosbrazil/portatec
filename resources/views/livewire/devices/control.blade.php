<section>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <a href="{{ route('app.devices.show', $device->id) }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
            <h1 class="m-0 mt-2">Controlar {{ $device->name }}</h1>
        </div>
    </div>

    <div
        class="rounded-[10px] border border-neutral-300 bg-white p-3.5"
        x-data="{
            placeId: {{ (int) $device->place_id }},
            deviceId: {{ $device->id }},
            statusByPin: {},
            pendingCommandId: null,
            pendingPin: null,
            ackTimeoutId: null,
            clickTime: null,
            MIN_BLOCK_MS: 3000,
            ACK_TIMEOUT_MS: 15000,
            ACKED_DISPLAY_MS: 2000,
            init() {
                const self = this;
                if (window.Echo && this.placeId) {
                    window.Echo.private(`Place.Device.Command.Ack.${this.placeId}`)
                        .listen('.PlaceDeviceCommandAck', (e) => {
                            const cmdId = e.command_id ?? e.commandId;
                            const matches = (e.deviceId == self.deviceId) &&
                                (String(e.pin) === String(self.pendingPin) || (cmdId && cmdId === self.pendingCommandId));
                            if (matches) {
                                self.setAcked(self.pendingPin);
                            }
                        });
                }
                window.addEventListener('command-sent', (ev) => {
                    if (ev.detail.deviceId == self.deviceId) {
                        self.pendingCommandId = ev.detail.commandId;
                        self.pendingPin = ev.detail.pin;
                        self.statusByPin[String(ev.detail.pin)] = 'sent';
                        if (self.ackTimeoutId) clearTimeout(self.ackTimeoutId);
                        self.ackTimeoutId = setTimeout(() => self.resetIdle(), self.ACK_TIMEOUT_MS);
                    }
                });
                window.addEventListener('command-failed', (ev) => {
                    if (ev.detail.deviceId == self.deviceId) {
                        self.resetIdle();
                    }
                });
            },
            status(pin) { return this.statusByPin[String(pin)] || 'idle'; },
            isBusy(pin) { return this.status(pin) !== 'idle'; },
            setSending(pin) {
                const pinStr = String(pin);
                this.statusByPin[pinStr] = 'sending';
                this.pendingPin = pin;
                this.clickTime = Date.now();
            },
            setAcked(pin) {
                this.statusByPin[String(pin)] = 'acked';
                if (this.ackTimeoutId) { clearTimeout(this.ackTimeoutId); this.ackTimeoutId = null; }
                setTimeout(() => this.resetIdle(), this.ACKED_DISPLAY_MS);
            },
            resetIdle() {
                const elapsed = this.clickTime ? Date.now() - this.clickTime : 0;
                if (elapsed < this.MIN_BLOCK_MS) {
                    setTimeout(() => this.resetIdle(), this.MIN_BLOCK_MS - elapsed);
                    return;
                }
                if (this.ackTimeoutId) { clearTimeout(this.ackTimeoutId); this.ackTimeoutId = null; }
                this.pendingCommandId = null;
                this.pendingPin = null;
                this.clickTime = null;
                const keys = Object.keys(this.statusByPin);
                keys.forEach(k => { this.statusByPin[k] = 'idle'; });
            },
            async triggerCommand(action, pin, buttonLabel) {
                const pinStr = String(pin);
                if (this.status(pinStr) !== 'idle') return;
                this.setSending(pinStr);
                try {
                    await this.$wire.sendCommand(action, pinStr);
                } catch (err) {
                    this.resetIdle();
                }
            }
        }"
    >
        <h2 class="mt-0">Ações disponíveis</h2>

        @forelse ($controllableFunctions as $function)
            <div class="mb-2.5 rounded-lg border border-neutral-300 p-3">
                <p class="m-0 mb-2.5 text-neutral-700">
                    {{ $function->type->label() }} (PIN {{ $function->pin }})
                </p>

                @if ($function->type->value === 'button')
                    <button
                        type="button"
                        @click="triggerCommand('push_button', '{{ $function->pin }}', 'Acionar')"
                        :disabled="isBusy('{{ $function->pin }}')"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <template x-if="status('{{ $function->pin }}') === 'idle'">
                            <span>Acionar</span>
                        </template>
                        <template x-if="status('{{ $function->pin }}') === 'sending'">
                            <span class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Enviando…
                            </span>
                        </template>
                        <template x-if="status('{{ $function->pin }}') === 'sent'">
                            <span class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Aguardando dispositivo…
                            </span>
                        </template>
                        <template x-if="status('{{ $function->pin }}') === 'acked'">
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
                        @click="triggerCommand('toggle', '{{ $function->pin }}', 'Alternar')"
                        :disabled="isBusy('{{ $function->pin }}')"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <template x-if="status('{{ $function->pin }}') === 'idle'">
                            <span>Alternar</span>
                        </template>
                        <template x-if="status('{{ $function->pin }}') === 'sending'">
                            <span class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Enviando…
                            </span>
                        </template>
                        <template x-if="status('{{ $function->pin }}') === 'sent'">
                            <span class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Aguardando dispositivo…
                            </span>
                        </template>
                        <template x-if="status('{{ $function->pin }}') === 'acked'">
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
            <p class="m-0 text-neutral-500">Nenhuma função controlável encontrada para este dispositivo.</p>
        @endforelse
    </div>
</section>
