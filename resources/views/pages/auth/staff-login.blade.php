<x-layouts::auth.staff :title="__('Ingreso de staff')">
    <div class="rounded-xl bg-white px-6 py-8 shadow-lg sm:px-10 sm:py-10">
        <x-centinela-logo class="mb-6" />

        <h1 class="mb-6 text-center text-lg font-medium text-neutral-700">
            {{ __('Ingreso de staff') }}
        </h1>

        @if ($errors->any())
            <div id="login-error" role="alert" class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ url('/staff/login') }}" class="flex flex-col gap-5">
            @csrf

            <div class="flex flex-col gap-2">
                <label for="email" class="text-sm font-medium text-neutral-800">
                    {{ __('Correo electrónico') }}
                </label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="nombre@ejemplo.com"
                    @if ($errors->any()) aria-invalid="true" aria-describedby="login-error" @endif
                    class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-neutral-900 placeholder:text-neutral-400 focus:border-[#8a5a1f] focus:outline-none focus:ring-2 focus:ring-[#8a5a1f]/40"
                />
            </div>

            <div class="flex flex-col gap-2">
                <label for="password" class="text-sm font-medium text-neutral-800">
                    {{ __('Contraseña') }}
                </label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    @if ($errors->any()) aria-invalid="true" aria-describedby="login-error" @endif
                    class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-neutral-900 placeholder:text-neutral-400 focus:border-[#8a5a1f] focus:outline-none focus:ring-2 focus:ring-[#8a5a1f]/40"
                />
            </div>

            <button
                type="submit"
                data-test="staff-login-button"
                class="mt-2 w-full rounded-md bg-[#8a5a1f] px-4 py-2.5 font-medium text-white transition hover:bg-[#744b19] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f] focus-visible:ring-offset-2"
            >
                {{ __('Ingresar') }}
            </button>
        </form>
    </div>
</x-layouts::auth.staff>
