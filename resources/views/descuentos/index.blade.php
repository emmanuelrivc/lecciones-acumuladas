<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Descuento general</h2>
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

            <p class="text-sm text-gray-600">
                Curso lectivo {{ $curso->anio }}. El descuento general se aplica solo a docentes guía. A cada una se le descuenta el menor entre
                los minutos indicados y su saldo, así que nadie queda en negativo.
            </p>

            {{-- Formulario --}}
            <form method="POST" action="{{ route('descuentos.store') }}"
                  class="card p-6 flex flex-wrap gap-4 items-end">
                @csrf
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Minutos a descontar</label>
                    <input type="number" name="minutos" min="1" value="{{ old('minutos') }}" required
                           class="input-base w-28">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Fecha</label>
                    <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" required
                           class="input-base">
                </div>
                <div class="flex-1 min-w-[12rem]">
                    <label class="block text-sm text-gray-600 mb-1">Motivo (obligatorio)</label>
                    <input type="text" name="motivo" value="{{ old('motivo') }}" required maxlength="255"
                           class="w-full input-base">
                </div>

                {{-- Qué docentes guía recibirán el descuento --}}
                @php
                    $marcadas = collect(old('docente_ids', $guias->where('saldo_minutos', '>', 0)->pluck('id')->all()))
                        ->map(fn ($id) => (int) $id)->all();
                @endphp
                <div class="w-full" x-data="{
                    todas(valor) { this.$root.querySelectorAll('input[type=checkbox]:not(:disabled)').forEach(c => c.checked = valor) }
                }">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <label class="block text-sm text-gray-600">Docentes guía a las que se les descuenta</label>
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-secondary btn-sm" @click="todas(true)">Marcar todas</button>
                            <button type="button" class="btn btn-secondary btn-sm" @click="todas(false)">Quitar todas</button>
                        </div>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($guias as $g)
                            <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 {{ $g->saldo_minutos > 0 ? '' : 'bg-gray-50 text-gray-400' }}">
                                <input type="checkbox" name="docente_ids[]" value="{{ $g->id }}"
                                       @checked(in_array($g->id, $marcadas) && $g->saldo_minutos > 0)
                                       @disabled($g->saldo_minutos <= 0)>
                                <span class="flex-1 text-sm">{{ $g->nombre }}</span>
                                <span class="text-xs">{{ $g->saldo_minutos }} min</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Las que tienen 0 minutos no se pueden marcar porque no hay nada que descontar.</p>
                </div>

                <button class="btn btn-primary">Aplicar descuento</button>
            </form>

            {{-- Descuentos ya aplicados --}}
            <div class="card overflow-x-auto">
                <table class="table-clean">
                    <thead>
                        <tr>
                            <th class="px-4 py-2">Fecha</th>
                            <th class="px-4 py-2">Motivo</th>
                            <th class="px-4 py-2 text-right">Minutos</th>
                            <th class="px-4 py-2">Alcance</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($descuentos as $d)
                            @php $r = $resumen[$d->id] ?? null; @endphp
                            <tr class="{{ $d->revertido ? 'text-gray-400' : '' }}">
                                <td class="px-4 py-2">{{ $d->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-2">{{ $d->motivo }}</td>
                                <td class="px-4 py-2 text-right">{{ $d->minutos }}</td>
                                <td class="px-4 py-2">
                                    @if ($r)
                                        <details>
                                            <summary class="cursor-pointer text-brand-700 hover:underline">
                                                {{ $r->docentes }} docentes · {{ abs((int) $r->total) }} min en total
                                            </summary>
                                            <ul class="mt-2 space-y-1 text-sm text-gray-700">
                                                @foreach ($afectadas[$d->id] ?? [] as $m)
                                                    <li class="flex justify-between gap-6">
                                                        <span>{{ $m->docente->nombre }}</span>
                                                        <span class="text-gray-500">{{ abs($m->minutos) }} min</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @else
                                        Nadie tenía saldo
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    @if ($d->revertido)
                                        Revertido
                                    @else
                                        <form method="POST" action="{{ route('descuentos.revertir', $d) }}">
                                            @csrf
                                            <button class="link-danger">Revertir</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-3 text-gray-600">Todavía no se ha aplicado ningún descuento.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
