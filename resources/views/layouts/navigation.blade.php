@php
    $esDirectora = Auth::user()->esDirectora();

    // [ruta, texto, patrón de ruta activa]
    $generales = [
        ['dashboard', 'Inicio', 'dashboard'],
        ['saldos.index', 'Saldos', 'saldos.*'],
        ['reportes.index', 'Reportes', 'reportes.*'],
    ];

    $soloDirectora = [
        ['ausencias.index', 'Ausencias', 'ausencias.index'],
        ['ausencias.create', 'Registrar ausencia', 'ausencias.create'],
        ['solicitudes.index', 'Solicitudes', 'solicitudes.*'],
        ['descuentos.index', 'Descuento general', 'descuentos.*'],
        ['curso.index', 'Curso lectivo', 'curso.*'],
        ['personal.index', 'Personal', 'personal.*'],
        ['respaldo.index', 'Respaldo', 'respaldo.*'],
    ];
@endphp

<nav aria-label="Menú principal" class="flex h-full flex-col gap-1 overflow-y-auto px-4 py-7">
    <a href="{{ route('dashboard') }}" class="block px-3 pb-5">
        <span class="leading-tight">
            <span class="block text-base font-bold tracking-tight text-gray-900">Control de lecciones</span>
            <span class="block text-xs text-gray-500">Escuela Los Ángeles</span>
        </span>
    </a>

    @foreach ($generales as [$ruta, $texto, $patron])
        <a href="{{ route($ruta) }}"
           @class([
               'rounded-[10px] px-3 py-2.5 text-sm transition',
               'bg-brand-100 font-semibold text-brand-600' => request()->routeIs($patron),
               'text-gray-600 hover:bg-gray-50' => ! request()->routeIs($patron),
           ])>{{ $texto }}</a>
    @endforeach

    @if ($esDirectora)
        <div class="mx-3 mb-1 mt-4 text-xs uppercase tracking-wide text-gray-400">Solo directora</div>

        @foreach ($soloDirectora as [$ruta, $texto, $patron])
            <a href="{{ route($ruta) }}"
               @class([
                   'rounded-[10px] px-3 py-2.5 text-sm transition',
                   'bg-brand-100 font-semibold text-brand-600' => request()->routeIs($patron),
                   'text-gray-600 hover:bg-gray-50' => ! request()->routeIs($patron),
               ])>{{ $texto }}</a>
        @endforeach
    @endif

    {{-- Usuario y salida --}}
    <div class="mt-auto border-t border-[#E8EBF2] pt-4">
        <div class="px-3">
            <div class="truncate text-sm font-semibold text-gray-900">{{ Auth::user()->name }}</div>
            <div class="truncate text-xs text-gray-500">{{ Auth::user()->email }}</div>
        </div>

        <a href="{{ route('profile.edit') }}"
           class="mt-2 block rounded-[10px] px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">Mi perfil</a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="block w-full rounded-[10px] px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50">
                Cerrar sesión
            </button>
        </form>
    </div>
</nav>
