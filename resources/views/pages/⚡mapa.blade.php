<?php

use App\Services\MapaZonasAfectadas;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::publico')] #[Title('Mapa de barrios afectados (Formosa Capital)')] class extends Component {
    /**
     * Barrios con al menos MapaZonasAfectadas::MINIMO_REPORTES reportes, ordenados por total.
     *
     * @var list<array{barrio: string, etiqueta: string, lat: float, lng: float, total: int}>
     */
    #[Locked]
    public array $zonas;

    /**
     * Encuadre inicial del mapa, calculado a partir de todos los barrios: [[sur, oeste], [norte, este]].
     *
     * @var array{0: array{0: float, 1: float}, 1: array{0: float, 1: float}}
     */
    #[Locked]
    public array $limites;

    public function mount(): void
    {
        $this->zonas = app(MapaZonasAfectadas::class)->obtener();
        $this->limites = MapaZonasAfectadas::limites();
    }
}; ?>

<div class="flex flex-col gap-6">
    @php
        $minimo = \App\Services\MapaZonasAfectadas::MINIMO_REPORTES;
    @endphp

    <div>
        <h1 class="text-2xl font-semibold text-neutral-900 sm:text-3xl">{{ __('Mapa de barrios afectados (Formosa Capital)') }}</h1>
        <p class="mt-2 text-neutral-600">
            {{ __('En qué barrios de la capital se reciben los mensajes que la comunidad reporta como sospechosos.') }}
        </p>
    </div>

    {{-- Aviso de precisión destacado: las ubicaciones de los barrios no están verificadas. --}}
    <div role="note" data-test="aviso-precision" class="flex items-start gap-3 rounded-lg border-2 border-yellow-400 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="mt-0.5 size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <path d="M12 3 2 20h20L12 3Z" />
            <path d="M12 10v4M12 17h.01" />
        </svg>
        <p>
            <strong class="font-semibold">{{ __('Ubicaciones aproximadas.') }}</strong>
            {{ __('Las ubicaciones de los barrios en este mapa son aproximadas y orientativas, no verificadas contra un catastro oficial: sirven para tener una idea general de la zona, no una ubicación exacta.') }}
        </p>
    </div>

    @if ($zonas === [])
        <div role="status" data-test="mapa-vacio" class="rounded-md border border-neutral-200 bg-white px-4 py-3 text-sm text-neutral-700">
            {{ __('Todavía no hay suficientes reportes con barrio para mostrar zonas. Cada barrio aparece cuando reúne al menos :minimo.', ['minimo' => $minimo]) }}
        </div>
    @endif

    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div
            wire:ignore
            x-data="{
                mapa: null,
                iniciar() {
                    this.mapa = window.crearMapa(this.$refs.mapa, @js($zonas), @js($limites));
                },
                init() {
                    window.crearMapa
                        ? this.iniciar()
                        : window.addEventListener('mapa:listo', () => this.iniciar(), { once: true });
                },
                destroy() {
                    this.mapa?.remove();
                },
            }"
        >
            <div
                x-ref="mapa"
                role="region"
                aria-label="{{ __('Mapa de Formosa Capital con los barrios que tienen reportes') }}"
                class="h-[26rem] w-full bg-neutral-200 sm:h-[32rem]"
            ></div>
        </div>
    </section>

    {{-- La misma información en texto, para quien no puede ver o usar el mapa. --}}
    <section aria-labelledby="zonas-titulo" class="rounded-xl bg-white p-5 shadow-sm sm:p-6">
        <h2 id="zonas-titulo" class="text-lg font-medium text-neutral-900">{{ __('Barrios con reportes') }}</h2>

        @if ($zonas === [])
            <p class="mt-2 text-sm text-neutral-500">{{ __('Ninguno por ahora.') }}</p>
        @else
            <ul class="mt-3 divide-y divide-neutral-100">
                @foreach ($zonas as $zona)
                    <li class="flex items-center justify-between py-2 text-sm" data-zona="{{ $zona['barrio'] }}">
                        <span class="font-medium text-neutral-900">{{ $zona['etiqueta'] }}</span>
                        <span class="tabular-nums text-neutral-600">
                            {{ trans_choice(':total reporte|:total reportes', $zona['total'], ['total' => number_format($zona['total'], 0, ',', '.')]) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <p class="text-xs text-neutral-500" data-test="nota-precision">
        {{ __('Cada círculo marca una referencia aproximada del barrio, no el lugar exacto de ningún reporte.') }}
        {{ __('Solo aparecen barrios con al menos :minimo reportes; los reportes sin barrio y los descartados por el equipo de moderación no se cuentan.', ['minimo' => $minimo]) }}
        {{ __('Mapa base: © OpenStreetMap. Para mostrarlo, tu navegador descarga las imágenes del mapa desde los servidores de OpenStreetMap.') }}
    </p>
</div>

@assets
    @vite('resources/js/mapa.js')
@endassets
