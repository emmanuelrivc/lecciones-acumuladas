<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Solicitudes de uso de minutos</h2>
    </x-slot>

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

            {{-- Anotar una solicitud nueva --}}
            <form method="POST" action="{{ route('solicitudes.store') }}"
                  class="card p-6 grid sm:grid-cols-4 gap-4 items-end">
                @csrf
                <div class="sm:col-span-2">
                    <label class="block text-sm text-gray-600 mb-1">Docente</label>
                    <select name="docente_id" required class="w-full input-base">
                        <option value="">{{ $docentes->isEmpty() ? 'Nadie tiene minutos acumulados' : 'Elija...' }}</option>
                        @foreach ($docentes as $d)
                            <option value="{{ $d->id }}" @selected(old('docente_id') == $d->id)>{{ $d->nombre }} ({{ $d->saldo_minutos }} min)</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Minutos</label>
                    <input type="number" name="minutos" min="1" value="{{ old('minutos', 40) }}" required
                           class="w-full input-base">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Día que los usaría</label>
                    <input type="date" name="fecha_uso" value="{{ old('fecha_uso') }}" required
                           class="w-full input-base">
                </div>
                <div class="sm:col-span-4">
                    <button class="btn btn-primary">Registrar solicitud</button>
                </div>
            </form>

            {{-- Pendientes --}}
            <div class="card overflow-x-auto">
                <div class="px-4 py-3 font-medium text-gray-800 border-b border-gray-100">
                    Pendientes ({{ $pendientes->count() }})
                </div>
                <table class="table-clean">
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($pendientes as $s)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ $s->docente->nombre }}</p>
                                    <p class="text-gray-600">{{ $s->minutos }} min · {{ $s->fecha_uso->format('d/m/Y') }}@if ($s->motivo) · {{ $s->motivo }}@endif</p>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2 justify-end items-center">
                                        @if ($s->saldo_actual >= $s->minutos)
                                            <form method="POST" action="{{ route('solicitudes.aprobar', $s) }}">
                                                @csrf
                                                <button class="btn btn-success btn-sm">Aprobar</button>
                                            </form>
                                        @else
                                            <span class="badge badge-amber">Ya no tiene saldo suficiente ({{ $s->saldo_actual }} min)</span>
                                        @endif
                                        <form method="POST" action="{{ route('solicitudes.rechazar', $s) }}" class="flex gap-2">
                                            @csrf
                                            <input type="text" name="nota" placeholder="Razón (rechazar o descartar)" required maxlength="255"
                                                   class="input-base">
                                            <button class="link-danger">{{ $s->saldo_actual >= $s->minutos ? 'Rechazar' : 'Descartar' }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-3 text-gray-600">No hay solicitudes pendientes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Resueltas --}}
            <div class="card overflow-x-auto">
                <div class="px-4 py-3 font-medium text-gray-800 border-b border-gray-100">Resueltas recientemente</div>
                <table class="table-clean">
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($resueltas as $s)
                            <tr>
                                <td class="px-4 py-2">{{ $s->docente->nombre }}</td>
                                <td class="px-4 py-2">{{ $s->minutos }} min · {{ $s->fecha_uso->format('d/m/Y') }}</td>
                                <td class="px-4 py-2">
                                    @if ($s->estado === 'aprobada')
                                        <span class="badge badge-green">Aprobada</span>
                                    @else
                                        <span class="badge badge-red">Rechazada</span>
                                        @if ($s->nota_resolucion)
                                            <span class="text-gray-500">({{ $s->nota_resolucion }})</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-3 text-gray-600">Todavía no se ha resuelto ninguna.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
