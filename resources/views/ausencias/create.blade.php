<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Registrar ausencia</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl space-y-6">

            {{-- Errores de validación --}}
            @if ($errors->any())
                <div class="alert alert-error">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- Paso 1: elegir docente y día (GET: solo consulta) --}}
            <form method="GET" action="{{ route('ausencias.create') }}"
                  class="card p-6 flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Docente que faltó</label>
                    <select name="docente_id" class="input-base" required>
                        <option value="">Elija...</option>
                        @foreach ($docentesAgrupados as $titulo => $lista)
                            <optgroup label="{{ $titulo }}">
                                @foreach ($lista as $d)
                                    @php $r = $rotulos[$d->id] ?? null; @endphp
                                    <option value="{{ $d->id }}" @selected($docente?->id === $d->id)
                                            @if ($r) style="color: {{ $r['texto'] }}; background-color: {{ $r['fondo'] }};" @endif>
                                        {{ $d->nombre }}@if ($r) · {{ $r['rotulo'] }}@endif
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Fecha</label>
                    <input type="date" name="fecha" value="{{ $fecha?->toDateString() }}"
                           class="input-base" required>
                </div>
                <button class="btn btn-primary">Ver lecciones</button>
            </form>

            @if ($docente)
                {{-- Paso 2: elegir quién cubre y guardar (POST: modifica datos) --}}
                <form method="POST" action="{{ route('ausencias.store') }}"
                      class="card overflow-x-auto">
                    @csrf
                    <input type="hidden" name="docente_id" value="{{ $docente->id }}">
                    <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">
                    <input type="hidden" name="parcial" value="1">

                    <p class="px-4 py-3 text-sm text-gray-600 border-b">
                        {{ $docente->nombre }} · {{ $fecha->translatedFormat('l d/m/Y') }}
                    </p>
                    @if (! $lecciones->isEmpty())
                        <p class="px-4 pt-3 text-sm text-gray-600">
                            Quite la marca de las lecciones en las que la docente <strong>sí vino</strong>:
                            esas no cuentan como ausencia.
                        </p>
                    @endif

                    @if ($lecciones->isEmpty())
                        <p class="p-4 text-sm text-gray-700">Esa docente no tiene lecciones ese día.</p>
                    @else
                        <table class="table-clean">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2">Faltó</th>
                                    <th class="px-4 py-2">Hora</th>
                                    <th class="px-4 py-2">Grupo</th>
                                    <th class="px-4 py-2">Materia</th>
                                    <th class="px-4 py-2">Quién cubre</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($lecciones as $h)
                                    @php
                                        // De 1° a 3° se propone la guía del grupo; de 4° a 6° la directora elige cada vez
                                        $porDefecto = ($h->grupo->nivel <= 3 && $h->grupo->guia_id !== $docente->id)
                                            ? $h->grupo->guia_id
                                            : null;
                                    @endphp
                                    <tr x-data="{ falto: true }" :class="falto ? '' : 'opacity-50'">
                                        <td class="px-4 py-2">
                                            <input type="checkbox" name="faltas[]" value="{{ $h->id }}" x-model="falto" checked
                                                   class="rounded border-gray-300" aria-label="Faltó a esta lección">
                                        </td>
                                        <td class="px-4 py-2">
                                            {{ substr($h->franja->hora_inicio, 0, 5) }} - {{ substr($h->franja->hora_fin, 0, 5) }}
                                        </td>
                                        <td class="px-4 py-2">{{ $h->grupo->nivel }}{{ $h->grupo->seccion }}</td>
                                        <td class="px-4 py-2">{{ $h->materia->nombre }}</td>
                                        <td class="px-4 py-2">
                                            <select name="cubre[{{ $h->id }}]" class="input-base" :disabled="!falto">
                                                <option value="">{{ $h->grupo->nivel <= 3 ? 'Nadie' : 'Elegir quién cubre...' }}</option>
                                                @php
                                                    $idsLibres = $libres[$h->id]['libres'] ?? [];
                                                    $idsQuiza = $libres[$h->id]['quiza'] ?? [];
                                                    // La guía del grupo va primero, en su propio bloque (si no es la que faltó)
                                                    $guiaId = ($h->grupo->guia_id && $h->grupo->guia_id !== $docente->id) ? $h->grupo->guia_id : null;
                                                    $guia = $guiaId ? $docentes->firstWhere('id', $guiaId) : null;
                                                    $estadoGuia = in_array($guiaId, $idsLibres) ? 'disponible'
                                                        : (in_array($guiaId, $idsQuiza) ? 'quizá disponible' : 'ocupada a esa hora');
                                                    $otras = $docentes->filter(fn ($d) => $d->id !== $docente->id && $d->id !== $guiaId
                                                        && ! in_array($d->id, $idsLibres) && ! in_array($d->id, $idsQuiza));
                                                @endphp
                                                @if ($guia)
                                                    <optgroup label="Guía del grupo {{ $h->grupo->nivel }}{{ $h->grupo->seccion }}">
                                                        <option value="{{ $guia->id }}" @selected($guia->id === $porDefecto)>{{ $guia->nombre }} ({{ $estadoGuia }})</option>
                                                    </optgroup>
                                                @endif
                                                <optgroup label="Disponibles en esta lección">
                                                    @foreach ($docentes->whereIn('id', $idsLibres)->where('id', '!=', $guiaId) as $d)
                                                        <option value="{{ $d->id }}" @selected($d->id === $porDefecto)>{{ $d->nombre }}</option>
                                                    @endforeach
                                                </optgroup>
                                                <optgroup label="Quizá disponibles (una de las dos guías del nivel)">
                                                    @foreach ($docentes->whereIn('id', $idsQuiza)->where('id', '!=', $guiaId) as $d)
                                                        <option value="{{ $d->id }}" @selected($d->id === $porDefecto)>{{ $d->nombre }}</option>
                                                    @endforeach
                                                </optgroup>
                                                <optgroup label="Ocupadas a esa hora">
                                                    @foreach ($otras as $d)
                                                        <option value="{{ $d->id }}" @selected($d->id === $porDefecto)>{{ $d->nombre }}</option>
                                                    @endforeach
                                                </optgroup>
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="p-4 space-y-3 border-t">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Motivo (obligatorio)</label>
                                <input type="text" name="motivo" value="{{ old('motivo') }}" required maxlength="255"
                                       class="w-full input-base">
                            </div>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="aviso_previo" value="1" class="rounded border-gray-300">
                                Avisó con anticipación
                            </label>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Justificación (opcional)</label>
                                <textarea name="justificacion" rows="2"
                                          class="w-full input-base">{{ old('justificacion') }}</textarea>
                            </div>
                            <button class="btn btn-primary">Guardar ausencia</button>
                        </div>
                    @endif
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
