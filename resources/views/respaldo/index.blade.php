<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Respaldo de la información</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl space-y-6">

            @if ($errors->any())
                <div class="alert alert-error">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="card p-6 space-y-3 text-sm text-gray-700">
                <p>
                    El respaldo es una <strong>copia completa</strong> de toda la información del sistema:
                    personal, horarios, ausencias, saldos y su historial.
                </p>
                <p>
                    Se descarga como un archivo con fecha y hora en el nombre. Si la computadora se daña
                    o algo se borra por error, ese archivo permite recuperar todo.
                </p>
                <ul class="list-disc ms-5 space-y-1">
                    <li>Haga un respaldo <strong>al final de cada semana</strong> y antes de cerrar el curso.</li>
                    <li>Guarde el archivo <strong>fuera de esta computadora</strong>: una memoria USB o un correo.</li>
                    <li>Contiene los nombres del personal: no lo comparta ni lo suba a sitios públicos.</li>
                </ul>
            </div>

            <form method="POST" action="{{ route('respaldo.descargar') }}">
                @csrf
                <button class="btn btn-primary">Descargar respaldo ahora</button>
            </form>
        </div>
    </div>
</x-app-layout>
