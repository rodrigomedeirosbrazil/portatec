<section>
    <div class="mb-4 flex items-center justify-between">
        <h1 class="m-0">Dispositivos Tuya</h1>
        <div class="flex gap-2">
            <a href="{{ route('app.tuya.connect') }}" class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-neutral-700 no-underline hover:bg-neutral-50">
                Atualizar conta
            </a>
            <form method="POST" action="{{ route('app.tuya.disconnect') }}" class="inline" onsubmit="return confirm('Desvincular sua conta Tuya?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="cursor-pointer rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-red-700 hover:bg-red-100">
                    Desvincular conta
                </button>
            </form>
        </div>
    </div>
    @if(request()->query('linked') === '1')
        <div class="mb-4 rounded-lg border border-green-300 bg-green-50 px-3 py-2.5 text-green-800">
            Conta vinculada com sucesso. Associe cada dispositivo a um local abaixo.
        </div>
    @endif
    @if(session('status'))
        <div class="mb-4 rounded-lg border border-green-300 bg-green-50 px-3 py-2.5 text-green-800">
            {{ session('status') }}
        </div>
    @endif
    <div class="grid gap-3">
        @forelse($devices as $device)
            <article class="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="m-0 text-lg">{{ $device->name }}</h2>
                        <p class="m-0 mt-1 text-sm text-neutral-500">
                            {{ $device->device_id }}
                            @if($device->category)
                                · {{ $device->category }}
                            @endif
                            · {{ $device->online ? 'Online' : 'Offline' }}
                        </p>
                    </div>
                    <form method="POST" action="{{ route('app.tuya.devices.assign-place') }}" class="flex items-center gap-2">
                        @csrf
                        <input type="hidden" name="tuya_device_id" value="{{ $device->id }}">
                        <label class="text-sm text-neutral-600">
                            Local:
                            <select name="place_id" class="ml-1 rounded-md border border-neutral-300 px-2 py-1.5 text-sm">
                                <option value="">— Nenhum —</option>
                                @foreach($places as $place)
                                    <option value="{{ $place->id }}" {{ (int) $device->place_id === (int) $place->id ? 'selected' : '' }}>
                                        {{ $place->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="cursor-pointer rounded-lg bg-primary-500 px-3 py-1.5 text-sm text-white hover:bg-primary-700">
                            Salvar
                        </button>
                    </form>
                </div>
            </article>
        @empty
            <p class="text-neutral-500">Nenhum dispositivo encontrado. Verifique se sua conta está vinculada e se há dispositivos no app Smart Life.</p>
        @endforelse
    </div>
</section>
