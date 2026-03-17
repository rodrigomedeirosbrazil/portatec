<section>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <a href="{{ route('app.devices.show', $device->id) }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
            <h1 class="m-0 mt-2">Controlar {{ $device->name }}</h1>
        </div>
    </div>

    @php
        $placeId = (int) ($device->places->first()?->id ?? $device->place_id ?? $device->placeDeviceFunctions()->value('place_id') ?? 0);
        $statusFunction = $device->getStatusFunction();
        $initialFunctionStatus = $statusFunction && $statusFunction->status !== null
            ? [$device->id . '-' . $statusFunction->pin => $statusFunction->status]
            : [];
    @endphp

    <div
        class="rounded-[10px] border border-neutral-300 bg-white p-3.5"
        data-initial-function-status="{{ e(json_encode($initialFunctionStatus)) }}"
        x-data="{
            placeId: {{ $placeId }},
            deviceId: {{ $device->id }},
            statusByKey: {},
            functionStatusByKey: {},
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
                const raw = this.$el.dataset.initialFunctionStatus;
                if (raw) {
                    try {
                        this.functionStatusByKey = JSON.parse(raw);
                    } catch (e) {}
                }
                if (window.Echo && this.placeId) {
                    window.Echo.private('Place.Device.Command.Ack.' + this.placeId)
                        .listen('.PlaceDeviceCommandAck', (e) => {
                            const cmdId = e.command_id ?? e.commandId;
                            const key = self.key(e.deviceId, String(e.pin));
                            const matches = (e.deviceId == self.deviceId) &&
                                (String(e.pin) === String(self.pendingPin) || (cmdId && cmdId === self.pendingCommandId));
                            if (matches) {
                                self.setAcked(self.deviceId, self.pendingPin);
                            } else {
                                self.statusByKey[key] = 'acked';
                                setTimeout(() => { self.statusByKey[key] = 'idle'; }, self.ACKED_DISPLAY_MS);
                            }
                        });
                    window.Echo.private('Place.Device.Function.Status.' + this.placeId)
                        .listen('.PlaceDeviceFunctionStatus', (e) => {
                            const key = self.key(e.deviceId, String(e.pin));
                            self.functionStatusByKey[key] = e.status;
                        });
                }
                window.addEventListener('command-sent', (ev) => {
                    if (ev.detail.deviceId == self.deviceId) {
                        self.pendingCommandId = ev.detail.commandId;
                        self.pendingDeviceId = ev.detail.deviceId;
                        self.pendingPin = ev.detail.pin;
                        self.statusByKey[self.key(ev.detail.deviceId, ev.detail.pin)] = 'sent';
                        if (self.ackTimeoutId) clearTimeout(self.ackTimeoutId);
                        self.ackTimeoutId = setTimeout(() => self.resetIdle(), self.ACK_TIMEOUT_MS);
                    }
                });
                window.addEventListener('command-failed', (ev) => {
                    if (ev.detail.deviceId == self.deviceId) {
                        const k = self.key(ev.detail.deviceId, ev.detail.pin);
                        self.statusByKey[k] = 'idle';
                        self.resetIdle();
                    }
                });
            },
            status(deviceId, pin) { return this.statusByKey[this.key(deviceId, pin)] || 'idle'; },
            statusLabel(key, initialLabel) {
                const v = this.functionStatusByKey[key];
                if (v === undefined) return initialLabel;
                return this.formatStatusValue(v);
            },
            formatStatusValue(v) {
                if (v === true || v === 1 || v === '1' || v === 'open' || v === 'on') return '{{ __('app.device_statuses.open') }}';
                if (v === false || v === 0 || v === '0' || v === 'closed' || v === 'off') return '{{ __('app.device_statuses.closed') }}';
                return String(v);
            },
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
            triggerCommand(deviceId, action, pin) {
                const k = this.key(deviceId, pin);
                if (this.statusByKey[k] !== 'idle') return;
                this.setSending(deviceId, pin);
            }
        }"
    >
        <h2 class="mt-0">Ações disponíveis</h2>

        @forelse ($controllableFunctions as $function)
            @php
                $wireClick = $function->type->value === 'button'
                    ? "sendCommand('push_button', '{$function->pin}')"
                    : "sendCommand('toggle', '{$function->pin}')";
            @endphp
            <x-device-control.function-row
                :device-id="$device->id"
                :control-function="$function"
                :place-id="$placeId"
                :status-function="$statusFunction"
                :wire-click="$wireClick"
            />
        @empty
            <p class="m-0 text-neutral-500">Nenhuma função controlável encontrada para este dispositivo.</p>
        @endforelse
    </div>
</section>
