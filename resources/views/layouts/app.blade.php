<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fuente -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div x-data="{ menu: false }" class="min-h-screen lg:flex">

            {{-- Fondo oscuro cuando el menú está abierto en el celular --}}
            <div x-show="menu" x-cloak @click="menu = false" class="fixed inset-0 z-30 bg-black/40 lg:hidden"></div>

            {{-- Barra lateral --}}
            <aside :class="menu ? 'translate-x-0' : '-translate-x-full'"
                   class="fixed inset-y-0 left-0 z-40 w-64 transform border-r border-[#E8EBF2] bg-white transition-transform lg:static lg:flex-none lg:translate-x-0">
                @include('layouts.navigation')
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                {{-- Barra superior solo en el celular --}}
                <div class="flex h-14 items-center gap-3 border-b border-[#E8EBF2] bg-white px-4 lg:hidden">
                    <button type="button" @click="menu = true" aria-label="Abrir menú" class="rounded-lg p-2 text-gray-600 hover:bg-gray-100">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <span class="font-semibold text-gray-900">Control de lecciones</span>
                </div>

                <!-- Título de la página -->
                @isset($header)
                    <header class="px-4 pt-8 sm:px-8">
                        {{ $header }}
                    </header>
                @endisset

                <!-- Contenido -->
                <main class="flex-1 px-4 pb-12 sm:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
