<?php

use App\Services\EstadisticasImpacto;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::publico')] #[Title('Impacto')] class extends Component {
    // DEPRECATED: solo lo usaba el mock. Con datos reales, el estado vacío aparece cuando las tablas están vacías.
    /**
     * MOCK: cambiá esto a `true` para ver el estado vacío (proyecto recién arrancado)
     * en el navegador. Los tests lo prueban pasando `mockVacio: true` a mount().
     */
    private const MOCK_VACIO = false;

    /**
     * Estadísticas con la forma exacta del contrato (solo agregados, sin análisis ni reportes individuales):
     * { total_analisis: int, total_reportes: int, casos_confirmados: int, distribucion_nivel: { seguro: int, dudoso: int, riesgo: int } }
     *
     * @var array{total_analisis: int, total_reportes: int, casos_confirmados: int, distribucion_nivel: array{seguro: int, dudoso: int, riesgo: int}}
     */
    #[Locked]
    public array $estadisticas;

    public function mount(): void
    {
        // Agregados reales de analisis, reportes y casos_confirmados (COUNT / GROUP BY en la base).
        $this->estadisticas = app(EstadisticasImpacto::class)->obtener();
    }

    /**
     * Niveles en el orden de la barra, con su cantidad y porcentaje (0 si no hay análisis).
     *
     * @return array<int, array{clave: string, etiqueta: string, cantidad: int, porcentaje: float, color: string}>
     */
    #[Computed]
    public function niveles(): array
    {
        $total = $this->estadisticas['total_analisis'];
        $etiquetas = ['seguro' => 'Seguro', 'dudoso' => 'Dudoso', 'riesgo' => 'Riesgo'];
        $colores = ['seguro' => 'bg-green-600', 'dudoso' => 'bg-yellow-400', 'riesgo' => 'bg-red-600'];

        return array_map(fn (string $clave) => [
            'clave' => $clave,
            'etiqueta' => $etiquetas[$clave],
            'cantidad' => $this->estadisticas['distribucion_nivel'][$clave],
            'porcentaje' => $total > 0 ? $this->estadisticas['distribucion_nivel'][$clave] / $total * 100 : 0.0,
            'color' => $colores[$clave],
        ], array_keys($etiquetas));
    }

    // DEPRECATED: ya no se usa, EstadisticasImpacto real lo reemplaza. Se deja como referencia.
    /**
     * MOCK: estadísticas de ejemplo en memoria. Backend las reemplaza por los agregados reales.
     * Con `$vacio` devuelve todo en cero para probar el estado de un proyecto recién arrancado.
     *
     * @return array{total_analisis: int, total_reportes: int, casos_confirmados: int, distribucion_nivel: array{seguro: int, dudoso: int, riesgo: int}}
     */
    private function estadisticasMock(bool $vacio): array
    {
        if ($vacio) {
            return [
                'total_analisis' => 0,
                'total_reportes' => 0,
                'casos_confirmados' => 0,
                'distribucion_nivel' => ['seguro' => 0, 'dudoso' => 0, 'riesgo' => 0],
            ];
        }

        // MOCK: ~7 % de los análisis se reportan y ~60 % de los reportes se confirman.
        // La distribución suma exactamente total_analisis y la mayoría es "seguro".
        return [
            'total_analisis' => 1243,
            'total_reportes' => 87,
            'casos_confirmados' => 52,
            'distribucion_nivel' => ['seguro' => 812, 'dudoso' => 289, 'riesgo' => 142],
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    @php
        $formatear = fn (int $numero) => number_format($numero, 0, ',', '.');
        $porcentaje = fn (float $valor) => number_format($valor, 1, ',', '.').' %';
        $sinDatos = $estadisticas['total_analisis'] === 0;
        $tarjetas = [
            ['id' => 'total-analisis', 'titulo' => __('Análisis realizados'), 'valor' => $estadisticas['total_analisis'], 'detalle' => __('Mensajes, links y QR revisados en el analizador.')],
            ['id' => 'total-reportes', 'titulo' => __('Reportes comunitarios'), 'valor' => $estadisticas['total_reportes'], 'detalle' => __('Enviados de forma anónima por la comunidad.')],
            ['id' => 'casos-confirmados', 'titulo' => __('Casos confirmados'), 'valor' => $estadisticas['casos_confirmados'], 'detalle' => __('Reportes verificados por el equipo de moderación.')],
        ];
    @endphp

    <div>
        <h1 class="text-2xl font-semibold text-neutral-900 sm:text-3xl">{{ __('Impacto') }}</h1>
        <p class="mt-2 text-neutral-600">
            {{ __('Cómo viene ayudando la comunidad a detectar estafas en Formosa.') }}
        </p>
    </div>

    @if ($sinDatos)
        <div role="status" data-test="impacto-vacio" class="rounded-md border border-neutral-200 bg-white px-4 py-3 text-sm text-neutral-700">
            {{ __('Todavía no hay estadísticas para mostrar. Los números se van a ir actualizando a medida que la comunidad analice y reporte contenido.') }}
            <a href="{{ route('home') }}" class="font-medium text-[#15803d] underline underline-offset-2 hover:text-[#16a34a] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16a34a]" wire:navigate>
                {{ __('Analizá un mensaje') }}
            </a>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ($tarjetas as $tarjeta)
            <section aria-labelledby="{{ $tarjeta['id'] }}-titulo" class="rounded-xl bg-white p-5 shadow-sm">
                <h2 id="{{ $tarjeta['id'] }}-titulo" class="text-sm font-medium text-neutral-600">{{ $tarjeta['titulo'] }}</h2>
                <p data-test="{{ $tarjeta['id'] }}" class="mt-2 text-3xl font-semibold tabular-nums text-neutral-900">
                    {{ $formatear($tarjeta['valor']) }}
                </p>
                <p class="mt-1 text-xs text-neutral-500">{{ $tarjeta['detalle'] }}</p>
            </section>
        @endforeach
    </div>

    <section aria-labelledby="distribucion-titulo" class="rounded-xl bg-white p-5 shadow-sm sm:p-6">
        <h2 id="distribucion-titulo" class="text-lg font-medium text-neutral-900">{{ __('Distribución por nivel') }}</h2>
        <p class="mt-1 text-sm text-neutral-600">{{ __('Resultado de todos los análisis realizados.') }}</p>

        <div
            role="img"
            aria-label="{{ $sinDatos
                ? __('Sin análisis todavía.')
                : collect($this->niveles)->map(fn ($nivel) => $porcentaje($nivel['porcentaje']).' '.Str::lower($nivel['etiqueta']))->implode(', ') }}"
            class="mt-4 flex h-4 w-full overflow-hidden rounded-full bg-neutral-200"
        >
            @unless ($sinDatos)
                @foreach ($this->niveles as $nivel)
                    @if ($nivel['cantidad'] > 0)
                        <div
                            data-segmento="{{ $nivel['clave'] }}"
                            class="h-full {{ $nivel['color'] }}"
                            style="width: {{ round($nivel['porcentaje'], 4) }}%"
                        ></div>
                    @endif
                @endforeach
            @endunless
        </div>

        <ul class="mt-4 grid gap-3 sm:grid-cols-3">
            @foreach ($this->niveles as $nivel)
                <li class="flex items-start gap-2">
                    <span class="mt-1 size-3 shrink-0 rounded-full {{ $nivel['color'] }}" aria-hidden="true"></span>
                    <div>
                        <p class="text-sm font-medium text-neutral-900">{{ __($nivel['etiqueta']) }}</p>
                        <p class="text-sm text-neutral-600">
                            <span data-test="nivel-{{ $nivel['clave'] }}" class="tabular-nums">{{ $formatear($nivel['cantidad']) }}</span>
                            <span class="text-neutral-400" aria-hidden="true">·</span>
                            <span class="tabular-nums">{{ $porcentaje($nivel['porcentaje']) }}</span>
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
</div>
