<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Lecciones acumuladas</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl">
            @if (session('exito'))
                <div class="mb-4 alert alert-success">{{ session('exito') }}</div>
            @endif
            @if (! $curso)
                <div class="card p-6 text-gray-700">
                    No hay un curso lectivo abierto.
                </div>
            @else
                <p class="mb-4 text-sm text-gray-600">
                    Curso lectivo {{ $curso->anio }} · vencen el {{ $curso->fecha_vencimiento->format('d/m/Y') }} ·
                    1 lección = 40 minutos
                </p>

                <div class="card overflow-x-auto">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">Docente</th>
                                <th class="px-4 py-3 text-right">Minutos</th>
                                <th class="px-4 py-3 text-right">Lecciones</th>
                                @if (auth()->user()->esDirectora())
                                    <th class="px-4 py-3 text-right">Acciones</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($docentes as $d)
                                <tr>
                                    <td class="px-4 py-2 text-gray-900">
                                        <a href="{{ route('saldos.show', $d) }}" class="font-medium text-brand-700 hover:underline">{{ $d->nombre }}</a>
                                    </td>
                                    <td class="px-4 py-2 text-right">{{ $d->saldo_minutos }}</td>
                                    <td class="px-4 py-2 text-right font-medium">
                                        {{ rtrim(rtrim(number_format($d->saldo_minutos / 40, 2, ',', ''), '0'), ',') }}
                                    </td>
                                    @if (auth()->user()->esDirectora())
                                        <td class="px-4 py-2 text-right">
                                            <a href="{{ route('saldos.show', $d) }}#usar" class="btn btn-secondary btn-sm">
                                                {{ $d->saldo_minutos > 0 ? 'Usar / modificar' : 'Modificar' }}
                                            </a>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
