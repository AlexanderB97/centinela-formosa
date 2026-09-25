<x-layouts::staff :title="__('Dashboard')">
    <div class="rounded-xl bg-white p-6 shadow-sm sm:p-8">
        <h1 class="text-2xl font-semibold text-neutral-900">
            {{ __('Bienvenido/a') }}@if (auth('staff')->user()), {{ auth('staff')->user()->nombre }}@endif
        </h1>
        <p class="mt-2 text-neutral-600">
            {{ __('Panel de staff de Centinela Formosa.') }}
        </p>
    </div>
</x-layouts::staff>
