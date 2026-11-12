<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Personal</h2>
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

            {{-- Agregar docente: siempre se indica qué es (materia y horario, o grupo que guía) --}}
            <form method="POST" action="{{ route('personal.store') }}" class="card p-6 space-y-5"
                  x-data="{ tipo: '{{ old('tipo', 'especialista') }}' }">
                @csrf

                <div>
                    <label class="block text-sm text-gray-600 mb-1">Nombre completo de la docente nueva</label>
                    <input type="text" name="nombre" value="{{ old('nombre') }}" required maxlength="255"
                           class="w-full input-base">
                </div>

                <div>
                    <p class="block text-sm text-gray-600 mb-2">¿Qué es?</p>
                    <div class="flex flex-wrap gap-6 text-sm text-gray-800">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="tipo" value="especialista" x-model="tipo"> Especialista (da una materia)
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="tipo" value="guia" x-model="tipo"> Guía de un grupo
                        </label>
                    </div>
                </div>

                {{-- Especialista: materia y horario semanal --}}
                <fieldset x-show="tipo === 'especialista'" :disabled="tipo !== 'especialista'" x-cloak class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Materia que da</label>
                        <select name="materia_id" class="input-base" required>
                            <option value="">Elija...</option>
                            @foreach ($materias as $m)
                                <option value="{{ $m->id }}" @selected((string) old('materia_id') === (string) $m->id)>{{ $m->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 mb-2">
                            Horario semanal: en cada hora donde da lección, elija el grupo. Deje "—" donde no da lección.
                        </p>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-gray-600">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Hora</th>
                                        @foreach ($dias as $nombreDia)
                                            <th class="px-3 py-2 text-left font-medium">{{ $nombreDia }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($franjas as $f)
                                        <tr>
                                            <td class="px-3 py-1.5 whitespace-nowrap text-gray-700">
                                                {{ substr($f->hora_inicio, 0, 5) }} - {{ substr($f->hora_fin, 0, 5) }}
                                            </td>
                                            @foreach ($dias as $dia => $nombreDia)
                                                <td class="px-3 py-1.5">
                                                    <select name="horario[{{ $dia }}][{{ $f->id }}]" class="input-base py-1 text-sm">
                                                        <option value="">—</option>
                                                        @foreach ($grupos as $g)
                                                            <option value="{{ $g->id }}" @selected((string) old("horario.{$dia}.{$f->id}") === (string) $g->id)>{{ $g->nivel }}{{ $g->seccion }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </fieldset>

                {{-- Guía: el grupo que guía (solo grupos sin guía activa) --}}
                <fieldset x-show="tipo === 'guia'" :disabled="tipo !== 'guia'" x-cloak>
                    @if ($gruposSinGuia->isEmpty())
                        <p class="text-sm text-gray-700">
                            Todos los grupos tienen guía. Para agregar una guía nueva, primero dé de baja a la guía que sale.
                        </p>
                    @else
                        <label class="block text-sm text-gray-600 mb-1">Grupo del que es guía</label>
                        <select name="grupo_id" class="input-base" required>
                            <option value="">Elija...</option>
                            @foreach ($gruposSinGuia as $g)
                                <option value="{{ $g->id }}" @selected((string) old('grupo_id') === (string) $g->id)>{{ $g->nivel }}{{ $g->seccion }}</option>
                            @endforeach
                        </select>
                    @endif
                </fieldset>

                <button class="btn btn-primary">Agregar docente</button>
            </form>

            {{-- Lista --}}
            <div class="card overflow-x-auto">
                <table class="table-clean">
                    <thead>
                        <tr>
                            <th class="px-4 py-2">Nombre</th>
                            <th class="px-4 py-2">Estado</th>
                            <th class="px-4 py-2 text-right">Saldo (min)</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($personal as $d)
                            <tr class="{{ $d->activo ? '' : 'text-gray-400' }}">
                                <td class="px-4 py-2">{{ $d->nombre }}</td>
                                <td class="px-4 py-2"><span class="badge {{ $d->activo ? 'badge-green' : 'badge-gray' }}">{{ $d->activo ? 'Activa' : 'Inactiva' }}</span></td>
                                <td class="px-4 py-2 text-right">{{ $d->saldo_minutos }}</td>
                                <td class="px-4 py-2 text-right">
                                    @if ($d->activo)
                                        <form method="POST" action="{{ route('personal.baja', $d) }}"
                                              onsubmit="return confirm('¿Dar de baja a {{ $d->nombre }}? Su saldo de {{ $d->saldo_minutos }} minutos quedará en cero.')">
                                            @csrf
                                            <button class="link-danger">Dar de baja</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('personal.reactivar', $d) }}">
                                            @csrf
                                            <button class="font-medium text-brand-700 hover:underline">Reactivar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
