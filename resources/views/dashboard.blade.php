<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Inicio</h2>
    </x-slot>

    <div class="max-w-5xl space-y-6 py-8">

        {{-- Estado del curso --}}
        <p class="flex flex-wrap items-center justify-between gap-2 rounded-[10px] bg-[#F1F3F8] px-4 py-3 text-sm text-brand-700">
            <span><strong>Curso lectivo {{ $curso->anio }}</strong> · {{ $curso->cerrado ? 'cerrado' : 'abierto' }}</span>
            <span>Los saldos vencen el {{ $curso->fecha_vencimiento->format('d/m/Y') }}</span>
        </p>

        {{-- Avisos --}}
        @if ($curso->cerrado)
            <div class="alert bg-gray-100 border-gray-200 text-gray-700">
                El curso lectivo {{ $curso->anio }} está <strong>cerrado</strong>: solo se puede consultar.
            </div>
        @elseif ($resumen['dias_para_vencer'] < 0)
            <div class="alert alert-error">
                Los minutos acumulados <strong>ya vencieron</strong> el {{ $curso->fecha_vencimiento->format('d/m/Y') }}.
                Revise si corresponde cerrar el curso.
            </div>
        @elseif ($resumen['dias_para_vencer'] <= 30)
            <div class="alert alert-warn">
                Los minutos acumulados vencen en <strong>{{ $resumen['dias_para_vencer'] }} días</strong>
                ({{ $curso->fecha_vencimiento->format('d/m/Y') }}).
            </div>
        @endif

        {{-- Acciones frecuentes (solo directora) --}}
        @if (Auth::user()->esDirectora())
            <section class="card space-y-4 p-6">
                <h3 class="text-lg font-semibold text-gray-900">Acciones frecuentes</h3>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('ausencias.create') }}" class="btn btn-primary">Registrar ausencia</a>
                    <a href="{{ route('solicitudes.index') }}" class="btn btn-primary">Solicitudes de uso</a>
                    <a href="{{ route('descuentos.index') }}" class="btn btn-secondary">Descuento general</a>
                </div>
            </section>
        @endif

        {{-- Resumen --}}
        <section class="space-y-3">
            <h3 class="text-lg font-semibold text-gray-900">Resumen del curso lectivo</h3>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="rounded-lg border border-[#E8EBF2] bg-brand-50 p-4">
                    <p class="text-xs text-brand-700">Minutos acumulados</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $resumen['minutos'] }}</p>
                    <p class="text-xs text-brand-700">≈ {{ number_format($resumen['minutos'] / 40, 1, ',', '') }} lecciones</p>
                </div>
                <div class="rounded-lg border border-[#E8EBF2] bg-brand-50 p-4">
                    <p class="text-xs text-brand-700">Docentes con saldo</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $resumen['docentes_con_saldo'] }}</p>
                </div>
                <div class="rounded-lg border border-[#E8EBF2] bg-brand-50 p-4">
                    <p class="text-xs text-brand-700">Ausencias este mes</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $resumen['ausencias_mes'] }}</p>
                </div>
                <div class="rounded-lg border border-[#E8EBF2] bg-[#F1F3F8] p-4">
                    <p class="text-xs text-gray-600">Días para el vencimiento</p>
                    <p class="text-3xl font-bold text-gray-700">{{ max($resumen['dias_para_vencer'], 0) }}</p>
                    <p class="text-xs text-gray-600">{{ $curso->fecha_vencimiento->format('d/m/Y') }}</p>
                </div>
            </div>
        </section>

        {{-- Últimas ausencias --}}
        <section class="card overflow-x-auto">
            <div class="flex items-center justify-between border-b border-[#E8EBF2] px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Últimas ausencias registradas</h3>
                @if (Auth::user()->esDirectora())
                    <a href="{{ route('ausencias.index') }}" class="text-sm font-semibold text-brand-600 hover:underline">Ver todas</a>
                @endif
            </div>
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Docente</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ultimasAusencias as $a)
                        <tr class="{{ $a->estado === 'anulada' ? 'text-gray-400' : '' }}">
                            <td class="px-4 py-3">{{ $a->fecha->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $a->docente->nombre }}</td>
                            <td class="px-4 py-3">{{ $a->motivo }}</td>
                            <td class="px-4 py-3">
                                <span class="badge {{ $a->estado === 'anulada' ? 'badge-gray' : 'badge-green' }}">
                                    {{ $a->estado === 'anulada' ? 'Anulada' : 'Activa' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-4 text-gray-600">Todavía no hay ausencias registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
</x-app-layout>
