<x-guest-layout>
    <div class="mb-6 space-y-1.5">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Control de lecciones acumuladas</h1>
        <p class="text-gray-500">Ingrese con su usuario para continuar.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <!-- Correo -->
        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="mt-1 block min-h-[44px] w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Contraseña -->
        <div>
            <x-input-label for="password" value="Contraseña" />
            <x-text-input id="password" class="mt-1 block min-h-[44px] w-full"
                          type="password"
                          name="password"
                          required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Recordarme -->
        <label for="remember_me" class="inline-flex items-center">
            <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-600" name="remember">
            <span class="ms-2 text-sm text-gray-600">Mantener la sesión iniciada</span>
        </label>

        <x-primary-button class="min-h-[48px] w-full text-base">Ingresar</x-primary-button>

        <p class="text-sm text-gray-500">
            ¿Olvidó su contraseña? Comuníquese con la persona que instaló el sistema para que se la restablezca. El acceso es solo para dirección.
        </p>
    </form>
</x-guest-layout>
