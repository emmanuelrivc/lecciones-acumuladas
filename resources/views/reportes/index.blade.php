<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Reportes</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl space-y-4">
            <p class="text-sm text-gray-600">Curso lectivo {{ $curso->anio }}. Se abren en una pestaña nueva, listos para imprimir.</p>

            <div class="card p-6 flex items-center justify-between">
                <div>
                    <p class="font-medium text-gray-800">Saldos del personal</p>
                    <p class="text-sm text-gray-600">Minutos acumulados de cada docente activa.</p>
                </div>
                <a href="{{ route('reportes.saldos') }}" target="_blank"
                   class="btn btn-primary">Abrir PDF</a>
            </div>

            <div class="card p-6 flex items-center justify-between">
                <div>
                    <p class="font-medium text-gray-800">Historial de ausencias</p>
                    <p class="text-sm text-gray-600">Todas las ausencias del curso, con fecha, motivo y estado.</p>
                </div>
                <a href="{{ route('reportes.ausencias') }}" target="_blank"
                   class="btn btn-primary">Abrir PDF</a>
            </div>
        </div>
    </div>
</x-app-layout>
