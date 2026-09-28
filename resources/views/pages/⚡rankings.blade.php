<?php

use App\Services\RankingConsultados;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::publico')] #[Title('Lo más consultado')] class extends Component {
    /**
     * Top de contenidos sospechosos más analizados, por tipo. Ya viene enmascarado y truncado.
     *
     * @var array{texto: list<array{contenido: string, veces: int, nivel: string, confirmado: bool}>, link: list<array{contenido: string, veces: int, nivel: string, confirmado: bool}>, qr: list<array{contenido: string, veces: int, nivel: string, confirmado: bool}>}
     */
    #[Locked]
    public array $ranking;

    public function mount(): void
    {
        $this->ranking = app(RankingConsultados::class)->obtener();
    }
}; ?>

<div class="flex flex-col gap-6">
    @php
        $secciones = [
            'texto' => ['titulo' => __('Mensajes'), 'icono' => '<path d="M21 12a8 8 0 0 1-11.6 7.14L4 20l1.1-4.2A8 8 0 1 1 21 12Z" />'],
            'link' => ['titulo' => __('Links'), 'icono' => '<path d="M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1" /><path d="M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1" />'],
            'qr' => ['titulo' => __('Códigos QR'), 'icono' => '<rect x="4" y="4" width="6" height="6" rx="1" /><rect x="14" y="4" width="6" height="6" rx="1" /><rect x="4" y="14" width="6" height="6" rx="1" /><path d="M14 14h2v2M20 14v2M14 20h6M18 18v2" />'],
        ];
        $niveles = [
            'riesgo' => ['etiqueta' => __('Riesgo'), 'clase' => 'bg-red-100 text-red-800'],
            'dudoso' => ['etiqueta' => __('Dudoso'), 'clase' => 'bg-yellow-100 text-yellow-900'],
        ];
    @endphp

    <div>
        <h1 class="text-2xl font-semibold text-neutral-900 sm:text-3xl">{{ __('Lo más consultado') }}</h1>
        <p class="mt-2 text-neutral-600">
            {{ __('Los contenidos sospechosos que más veces se analizaron en Centinela Formosa. Si te llega alguno de estos, no respondas ni abras los enlaces.') }}
        </p>
        <p class="mt-2 text-xs text-neutral-500">
            {{ __('Solo aparecen contenidos analizados al menos :minimo veces. Ocultamos emails, teléfonos y números largos, y el nivel es el más alto que se detectó.', ['minimo' => App\Services\RankingConsultados::MINIMO_REPETICIONES]) }}
        </p>
    </div>

    @foreach ($secciones as $tipo => $seccion)
        <section aria-labelledby="ranking-{{ $tipo }}-titulo" data-ranking="{{ $tipo }}" class="rounded-xl bg-white p-5 shadow-sm sm:p-6">
            <h2 id="ranking-{{ $tipo }}-titulo" class="flex items-center gap-2 text-lg font-medium text-neutral-900">
                <span class="flex size-8 items-center justify-center rounded-full bg-[#16a34a]/10 text-[#16a34a]" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">{!! $seccion['icono'] !!}</svg>
                </span>
                {{ $seccion['titulo'] }}
            </h2>

            @if ($ranking[$tipo] === [])
                <p class="mt-3 text-sm text-neutral-500" data-test="ranking-{{ $tipo }}-vacio">
                    {{ __('Todavía no hay contenidos repetidos en esta categoría.') }}
                </p>
            @else
                <ol class="mt-4 flex flex-col gap-3">
                    @foreach ($ranking[$tipo] as $posicion => $item)
                        <li class="flex gap-3 rounded-lg border border-neutral-200 p-3 sm:p-4">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-[#12151a] text-sm font-semibold text-white" aria-hidden="true">
                                {{ $posicion + 1 }}
                            </span>
                            <div class="min-w-0 flex-1">
                                {{-- Contenido reportado: siempre como texto plano, nunca como link clickeable. --}}
                                <p class="whitespace-pre-wrap break-words font-mono text-sm text-neutral-900">{{ $item['contenido'] }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                    <span class="rounded-full px-2.5 py-0.5 font-medium {{ $niveles[$item['nivel']]['clase'] }}">
                                        {{ __('Nivel') }}: {{ $niveles[$item['nivel']]['etiqueta'] }}
                                    </span>
                                    <span class="text-neutral-600">
                                        {{ trans_choice('Analizado :veces vez|Analizado :veces veces', $item['veces'], ['veces' => number_format($item['veces'], 0, ',', '.')]) }}
                                    </span>
                                    @if ($item['confirmado'])
                                        <span class="rounded-full bg-[#12151a] px-2.5 py-0.5 font-medium text-white" data-test="confirmado">
                                            {{ __('Confirmado como estafa') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endforeach
</div>
