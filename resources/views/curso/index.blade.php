<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Curso lectivo {{ $curso->anio }}</h2>
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

            <div class="card p-6 space-y-2">
                <p class="text-sm text-gray-600">Estado</p>
                <p class="text-2xl font-semibold {{ $curso->cerrado ? 'text-gray-500' : 'text-green-700' }}">
                    {{ $curso->cerrado ? 'Cerrado (solo consulta)' : 'Abierto' }}
                </p>
                <p class="text-sm text-gray-600">
                    Los saldos vencen el {{ $curso->fecha_vencimiento->format('d/m/Y') }}.
                    Minutos acumulados hoy entre todo el personal: <strong>{{ $minutosAcumulados }}</strong>
                    ({{ rtrim(rtrim(number_format($minutosAcumulados / 40, 2, ',', ''), '0'), ',') }} lecciones).
                </p>
            </div>

            @if (! $curso->cerrado)
                <form method="POST" action="{{ route('curso.cerrar') }}"
                      class="card p-6 space-y-3">
                    @csrf
                    <p class="text-sm text-gray-700">
                        Al cerrar el curso nadie podrá registrar ausencias, usos, descuentos ni ajustes.
                        Los saldos y el historial seguirán disponibles para consulta.
                    </p>
                    <div class="flex flex-wrap gap-4 items-end">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Escriba {{ $curso->anio }} para confirmar</label>
                            <input type="text" name="confirmacion" required autocomplete="off"
                                   class="input-base w-32">
                        </div>
                        <button class="btn btn-danger">Cerrar curso</button>
                    </div>
                </form>
            @else
                <form method="POST" action="{{ route('curso.reabrir') }}"
                      class="card p-6 space-y-3">
                    @csrf
                    <p class="text-sm text-gray-700">Si el curso se cerró por error, puede reabrirlo.</p>
                    <button class="btn btn-primary">Reabrir curso</button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
