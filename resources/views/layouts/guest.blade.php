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
    <body class="font-sans text-gray-900 antialiased">
        <div class="flex min-h-screen flex-col bg-gradient-to-br from-brand-900 via-brand-600 to-brand-500">
            <div class="flex flex-1 items-center justify-center px-4 py-12">
                <div class="w-full max-w-md rounded-lg border-t-[6px] border-brand-600 bg-white px-8 py-10 shadow-xl">
                    <p class="mb-8 text-center text-4xl font-extrabold leading-tight tracking-tight text-brand-700">
                        Escuela Los Ángeles
                    </p>

                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
