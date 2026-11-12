<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Ausencias registradas</h2>
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

            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-600">Curso lectivo {{ $curso->anio }}</p>
                <a href="{{ route('ausencias.create') }}"
                   class="btn btn-primary">Registrar ausencia</a>
            </div>

            <div class="card overflow-x-auto">
                <table class="table-clean">
                    <thead>
                        <tr>
                            <th class="px-4 py-2">Fecha</th>
                            <th class="px-4 py-2">Docente</th>
                            <th class="px-4 py-2">Motivo</th>
                            <th class="px-4 py-2 text-right">Lecciones</th>
                            <th class="px-4 py-2">Estado</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($ausencias as $a)
                            <tr class="{{ $a->estado === 'anulada' ? 'text-gray-400' : '' }}">
                                <td class="px-4 py-2">{{ $a->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-2">{{ $a->docente->nombre }}</td>
                                <td class="px-4 py-2">{{ $a->motivo }}</td>
                                <td class="px-4 py-2 text-right">{{ $a->lecciones_count }}</td>
                                <td class="px-4 py-2"><span class="badge {{ $a->estado === 'anulada' ? 'badge-gray' : 'badge-green' }}">{{ $a->estado === 'anulada' ? 'Anulada' : 'Activa' }}</span></td>
                                <td class="px-4 py-2 text-right">
                                    @if ($a->estado === 'activa')
                                        <form method="POST" action="{{ route('ausencias.anular', $a) }}">
                                            @csrf
                                            <button class="link-danger">Anular</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-3 text-gray-600">Todavía no hay ausencias registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
