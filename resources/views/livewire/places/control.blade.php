<section>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <a href="{{ route('app.places.show', $place->id) }}" class="text-primary-500 no-underline hover:text-primary-700">&larr; Voltar</a>
            <h1 class="m-0 mt-2">Controle – {{ $place->name }}</h1>
        </div>
    </div>

    @php
        $initialFunctionStatusPlace = collect($place->devices)->flatMap(function ($d) { $sf = $d->getStatusFunction(); return $sf && $sf->status !== null ? [$d->id . '-' . $sf->pin => $sf->status] : []; })->all();
    @endphp
    <div
        class="space-y-4"
        data-initial-function-status="{{ e(json_encode($initialFunctionStatusPlace)) }}"
        x-data="{
            placeId: {{ (int) $place->id }},
            statusByKey: {},
            functionStatusByKey: {},
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
                const raw = this.$el.dataset.initialFunctionStatus;
                if (raw) {
                    try {
                        this.functionStatusByKey = JSON.parse(raw);
                    } catch (e) {}
                }
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
                    window.Echo.private('Place.Device.Function.Status.' + this.placeId)
                        .listen('.PlaceDeviceFunctionStatus', (e) => {
                            const key = self.key(e.deviceId, String(e.pin));
                            self.functionStatusByKey[key] = e.status;
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
                if (this.status(deviceId, pin) !== 'idle') return;
                this.setSending(deviceId, pin);
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
                    @php
                        $wireClick = $function->type->value === 'button'
                            ? "sendCommand({$device->id}, 'push_button', '{$function->pin}')"
                            : "sendCommand({$device->id}, 'toggle', '{$function->pin}')";
                    @endphp
                    <x-device-control.function-row
                        :device-id="$device->id"
                        :control-function="$function"
                        :place-id="$place->id"
                        :status-function="$device->getStatusFunction()"
                        :wire-click="$wireClick"
                    />
                @empty
                    @if ($device->isTuyaLock())
                        <p class="m-0 text-neutral-500">Esta fechadura Tuya recebe os PINs temporários pelos Access Codes deste local.</p>
                    @else
                        <p class="m-0 text-neutral-500">Nenhuma função controlável para este dispositivo.</p>
                    @endif
                @endforelse
            </div>
        @empty
            <p class="m-0 rounded-[10px] border border-neutral-300 bg-white p-3.5 text-neutral-500">Nenhum dispositivo neste local.</p>
        @endforelse
    </div>
</section>
