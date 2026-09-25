<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::staff')] #[Title('Reportes')] class extends Component {
    /**
     * MOCK: el reporte con este ID simula que otro moderador lo resolvió mientras
     * estaba abierto, para poder ver el 422 "ya no está pendiente" en el navegador.
     */
    private const ID_CONFLICTO_MOCK = 3;

    /**
     * MOCK: datos de ejemplo en memoria solo para maquetar. Backend los reemplaza por
     * GET /staff/reportes (reportes pendientes con su análisis vinculado completo).
     * Los reportes son anónimos: no incluyen ningún dato de quien reportó.
     *
     * @var array<int, array{id: int, comentario: string|null, estado: string, analisis: array{tipo: string, contenido: string, nivel: string, razones: array<int, string>, explicacion: string}}>
     */
    #[Locked]
    public array $reportes = [
        [
            'id' => 1,
            'comentario' => 'Me llegó por WhatsApp de un número que no conozco, decía ser del banco.',
            'estado' => 'pendiente',
            'analisis' => [
                'tipo' => 'texto',
                'contenido' => "URGENTE: tu cuenta bancaria fue suspendida.\nPara reactivarla respondé este mensaje con tu número de tarjeta y tu clave antes de las 18 hs.",
                'nivel' => 'riesgo',
                'razones' => [
                    'Menciona una entidad bancaria o datos de cuenta.',
                    'Pide datos sensibles como claves o códigos.',
                    'Genera sensación de urgencia para que actúes rápido.',
                ],
                'explicacion' => 'El mensaje reúne varias señales típicas de estafa: se hace pasar por un banco, pide la clave y presiona con un plazo corto.',
            ],
        ],
        [
            'id' => 2,
            'comentario' => null,
            'estado' => 'pendiente',
            'analisis' => [
                'tipo' => 'link',
                'contenido' => 'http://mi-cuenta-premios-oficial-2024.com/ingresar',
                'nivel' => 'dudoso',
                'razones' => [
                    'El dominio tiene muchos números, algo poco habitual en sitios legítimos.',
                    'El dominio tiene muchos guiones, algo común en sitios que imitan a otros.',
                    'El enlace no usa conexión segura (https).',
                ],
                'explicacion' => 'El enlace no muestra señales claras de estafa, pero su dominio tiene características que suelen usarse para imitar sitios oficiales.',
            ],
        ],
        [
            'id' => self::ID_CONFLICTO_MOCK,
            'comentario' => 'El QR estaba pegado sobre la carta de un bar, me pareció raro.',
            'estado' => 'pendiente',
            'analisis' => [
                'tipo' => 'qr',
                'contenido' => 'https://carta.bar-ejemplo.com.ar/menu',
                'nivel' => 'seguro',
                'razones' => [
                    'No se detectaron patrones típicos de estafa.',
                    'Los enlaces no presentan señales sospechosas.',
                ],
                'explicacion' => 'No encontramos señales de riesgo en el contenido del código QR.',
            ],
        ],
    ];

    /** Mensaje de la última acción y su tipo: 'exito' o 'conflicto' (422). */
    public ?string $aviso = null;
    public ?string $tipoAviso = null;

    /** Cambia en cada acción para que el aviso se vuelva a montar y reciba el foco. */
    #[Locked]
    public int $numeroAviso = 0;

    /**
     * @return array<int, array{id: int, comentario: string|null, estado: string, analisis: array<string, mixed>}>
     */
    #[Computed]
    public function pendientes(): array
    {
        return array_values(array_filter($this->reportes, fn (array $reporte) => $reporte['estado'] === 'pendiente'));
    }

    public function confirmar(int $id): void
    {
        $this->resolver($id, 'confirmado');
    }

    public function descartar(int $id): void
    {
        $this->resolver($id, 'descartado');
    }

    private function resolver(int $id, string $nuevoEstado): void
    {
        // TODO: backend reemplaza esta llamada por POST /staff/reportes/{id}/confirmar o /descartar.
        [$status, $mensaje] = $this->resolverMock($id, $nuevoEstado);

        $this->tipoAviso = $status === 422 ? 'conflicto' : 'exito';
        $this->aviso = $mensaje;
        $this->numeroAviso++;
    }

    /**
     * MOCK: simula POST /staff/reportes/{id}/confirmar|descartar en memoria. Cambia el estado
     * del reporte (no lo borra) y devuelve [status, mensaje] imitando el 200 y el 422 del contrato.
     * No implementa casos_confirmados ni nada de lo que backend haga al confirmar.
     *
     * @return array{0: int, 1: string}
     */
    private function resolverMock(int $id, string $nuevoEstado): array
    {
        // MOCK: demora artificial breve para que se vea el estado de carga de los botones.
        if (! app()->runningUnitTests()) {
            usleep(random_int(300, 600) * 1000);
        }

        $indice = array_search($id, array_column($this->reportes, 'id'), true);

        if ($indice === false) {
            return [422, "No encontramos el reporte #{$id}. Puede que ya se haya resuelto."];
        }

        // MOCK: otro moderador resolvió este reporte justo antes que vos.
        if ($id === self::ID_CONFLICTO_MOCK && $this->reportes[$indice]['estado'] === 'pendiente') {
            $this->reportes[$indice]['estado'] = 'confirmado';
        }

        if ($this->reportes[$indice]['estado'] !== 'pendiente') {
            return [422, "El reporte #{$id} ya no está pendiente: otro moderador ya lo resolvió. Lo sacamos de tu cola."];
        }

        $this->reportes[$indice]['estado'] = $nuevoEstado;

        return [200, "Reporte #{$id} {$nuevoEstado}."];
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold text-neutral-900">
            {{ __('Reportes pendientes') }}
            <span class="text-neutral-500">({{ count($this->pendientes) }})</span>
        </h1>
        <p class="mt-1 text-sm text-neutral-600">
            {{ __('Revisá cada reporte con su análisis y decidí si confirmarlo o descartarlo.') }}
        </p>
    </div>

    <div aria-live="polite">
        @if ($aviso)
            <div
                wire:key="aviso-{{ $numeroAviso }}"
                role="status"
                tabindex="-1"
                x-init="$nextTick(() => $el.focus())"
                data-aviso="{{ $tipoAviso }}"
                @class([
                    'rounded-md border px-4 py-3 text-sm focus:outline-none',
                    'border-green-200 bg-green-50 text-green-800' => $tipoAviso === 'exito',
                    'border-sky-200 bg-sky-50 text-sky-900' => $tipoAviso === 'conflicto',
                ])
            >
                {{ $aviso }}
            </div>
        @endif
    </div>

    @forelse ($this->pendientes as $reporte)
        @php
            $analisis = $reporte['analisis'];
            $tipos = ['texto' => __('Texto'), 'link' => __('Link'), 'qr' => __('Foto de QR')];
            $niveles = [
                'riesgo' => ['etiqueta' => __('Riesgo'), 'clase' => 'bg-red-100 text-red-800'],
                'dudoso' => ['etiqueta' => __('Dudoso'), 'clase' => 'bg-yellow-100 text-yellow-900'],
                'seguro' => ['etiqueta' => __('Seguro'), 'clase' => 'bg-green-100 text-green-800'],
            ];
            $nivel = $niveles[$analisis['nivel']];
            $objetivo = "confirmar({$reporte['id']}), descartar({$reporte['id']})";
        @endphp

        <article
            wire:key="reporte-{{ $reporte['id'] }}"
            data-reporte="{{ $reporte['id'] }}"
            aria-labelledby="reporte-{{ $reporte['id'] }}-titulo"
            class="rounded-xl bg-white p-6 shadow-sm"
        >
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="reporte-{{ $reporte['id'] }}-titulo" class="mr-auto text-lg font-medium text-neutral-900">
                    {{ __('Reporte') }} #{{ $reporte['id'] }}
                </h2>
                <span class="rounded-full bg-neutral-200 px-2.5 py-0.5 text-xs font-medium text-neutral-800">
                    {{ $tipos[$analisis['tipo']] }}
                </span>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $nivel['clase'] }}">
                    {{ __('Nivel') }}: {{ $nivel['etiqueta'] }}
                </span>
            </div>

            <h3 class="mt-5 text-sm font-semibold text-neutral-900">{{ __('Contenido analizado') }}</h3>
            {{-- Contenido reportado: siempre como texto plano, nunca como link clickeable. --}}
            <div class="mt-2 rounded-md border border-neutral-200 bg-neutral-50 p-3">
                <p class="whitespace-pre-wrap break-words font-mono text-sm text-neutral-900">{{ $analisis['contenido'] }}</p>
            </div>
            @if ($analisis['tipo'] !== 'texto')
                <p class="mt-1 text-xs text-neutral-500">{{ __('El enlace se muestra como texto y no es clickeable, por seguridad.') }}</p>
            @endif

            <h3 class="mt-5 text-sm font-semibold text-neutral-900">{{ __('Por qué') }}</h3>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-neutral-800">
                @foreach ($analisis['razones'] as $razon)
                    <li>{{ $razon }}</li>
                @endforeach
            </ul>

            <h3 class="mt-5 text-sm font-semibold text-neutral-900">{{ __('Explicación') }}</h3>
            <p class="mt-2 text-sm text-neutral-800">{{ $analisis['explicacion'] }}</p>

            <h3 class="mt-5 text-sm font-semibold text-neutral-900">{{ __('Comentario del reporte') }}</h3>
            @if (filled($reporte['comentario']))
                <p class="mt-2 text-sm text-neutral-800">{{ $reporte['comentario'] }}</p>
            @else
                <p class="mt-2 text-sm italic text-neutral-500">{{ __('Sin comentario') }}</p>
            @endif

            <div class="mt-6 flex flex-col-reverse gap-2 border-t border-neutral-200 pt-4 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    wire:click="descartar({{ $reporte['id'] }})"
                    wire:loading.attr="disabled"
                    wire:target="{{ $objetivo }}"
                    aria-label="{{ __('Descartar reporte') }} #{{ $reporte['id'] }}"
                    data-test="descartar-{{ $reporte['id'] }}"
                    class="rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-800 hover:bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f] focus-visible:ring-offset-2 disabled:opacity-60"
                >
                    {{ __('Descartar') }}
                </button>
                <button
                    type="button"
                    wire:click="confirmar({{ $reporte['id'] }})"
                    wire:loading.attr="disabled"
                    wire:target="{{ $objetivo }}"
                    aria-label="{{ __('Confirmar reporte') }} #{{ $reporte['id'] }}"
                    data-test="confirmar-{{ $reporte['id'] }}"
                    class="rounded-md bg-[#8a5a1f] px-4 py-2 text-sm font-medium text-white hover:bg-[#744b19] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f] focus-visible:ring-offset-2 disabled:opacity-60"
                >
                    {{ __('Confirmar') }}
                </button>
            </div>
        </article>
    @empty
        <div class="rounded-xl bg-white p-10 text-center shadow-sm" data-test="sin-reportes">
            <p class="text-lg font-medium text-neutral-900">{{ __('No hay nada para revisar') }}</p>
            <p class="mt-1 text-sm text-neutral-600">{{ __('Cuando alguien reporte un análisis, va a aparecer acá.') }}</p>
        </div>
    @endforelse
</div>
