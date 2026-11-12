<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $docente->nombre }}</h2>
    </x-slot>

    @php
        $etiquetas = [
            'acumulacion' => 'Cubrió una lección',
            'uso'         => 'Usó minutos',
            'descuento'   => 'Descuento general',
            'ajuste'      => 'Ajuste manual',
            'reversion'   => 'Reversión',
        ];
    @endphp

    <div class="py-8">
        <div class="max-w-5xl space-y-6">

            @if (session('exito'))
                <div class="alert alert-success">{{ session('exito') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-error">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- Saldo actual --}}
            <div class="card p-6">
                <p class="text-sm text-gray-600">Saldo actual · curso {{ $curso->anio }}</p>
                <p class="text-3xl font-semibold text-gray-900">{{ $saldo }} minutos</p>
                <p class="text-sm text-gray-600">
                    = {{ rtrim(rtrim(number_format($saldo / 40, 2, ',', ''), '0'), ',') }} lecciones
                </p>
            </div>

            {{-- Formulario para usar minutos: solo la directora lo ve --}}
            @if (auth()->user()->esDirectora())
                <form id="usar" method="POST" action="{{ route('saldos.usar', $docente) }}"
                      class="card p-6 flex flex-wrap gap-4 items-end">
                    @csrf
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Minutos a usar</label>
                        <input type="number" name="minutos" min="1" value="{{ old('minutos') }}" required
                               class="input-base w-28">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Fecha</label>
                        <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" required
                               class="input-base">
                    </div>
                    <div class="flex-1 min-w-[12rem]">
                        <label class="block text-sm text-gray-600 mb-1">Nota (opcional)</label>
                        <input type="text" name="nota" value="{{ old('nota') }}" maxlength="255"
                               class="w-full input-base">
                    </div>
                    <button class="btn btn-primary">Registrar uso</button>
                </form>

                {{-- Ajuste manual: suma (positivo) o resta (negativo) --}}
                <form method="POST" action="{{ route('saldos.ajustar', $docente) }}"
                      class="card p-6 flex flex-wrap gap-4 items-end">
                    @csrf
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Ajuste (+ suma, - resta)</label>
                        <input type="number" name="minutos" required
                               class="input-base w-28">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Fecha</label>
                        <input type="date" name="fecha" value="{{ now()->toDateString() }}" required
                               class="input-base">
                    </div>
                    <div class="flex-1 min-w-[12rem]">
                        <label class="block text-sm text-gray-600 mb-1">Motivo (obligatorio)</label>
                        <input type="text" name="motivo" required maxlength="255"
                               class="w-full input-base">
                    </div>
                    <button class="btn btn-primary">Registrar ajuste</button>
                </form>
            @endif

            {{-- Historial --}}
            <div class="card overflow-x-auto">
                <table class="table-clean">
                    <thead>
                        <tr>
                            <th class="px-4 py-2">Fecha</th>
                            <th class="px-4 py-2">Qué pasó</th>
                            <th class="px-4 py-2 text-right">Minutos</th>
                            <th class="px-4 py-2">Nota</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($movimientos as $m)
                            <tr>
                                <td class="px-4 py-2">{{ \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') }}</td>
                                <td class="px-4 py-2">{{ $etiquetas[$m->tipo] ?? $m->tipo }}</td>
                                <td class="px-4 py-2 text-right {{ $m->minutos < 0 ? 'text-red-600' : 'text-green-700' }}">
                                    {{ $m->minutos > 0 ? '+' : '' }}{{ $m->minutos }}
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $m->nota }}</td>
                                <td class="px-4 py-2 text-right">
                                    @if (auth()->user()->esDirectora() && in_array($m->tipo, ['uso', 'ajuste']) && ! in_array($m->id, $revertidos))
                                        <form method="POST" action="{{ route('movimientos.revertir', $m) }}">
                                            @csrf
                                            <button class="link-danger">Revertir</button>
                                        </form>
                                    @elseif (in_array($m->id, $revertidos))
                                        <span class="text-gray-400">Revertido</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-3 text-gray-600">Todavía no hay movimientos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <a href="{{ route('saldos.index') }}" class="text-sm text-gray-600 underline">← Volver a la lista</a>
        </div>
    </div>
</x-app-layout>
