<?php

use App\Models\Noticia;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * Últimas noticias de la portada. Es un componente hijo aparte a propósito: el analizador se vuelve
 * a renderizar en cada análisis y este no, así que las noticias se consultan una sola vez por visita.
 */
new class extends Component {
    public const CANTIDAD = 3;

    /**
     * @return Collection<int, Noticia>
     */
    #[Computed]
    public function noticias(): Collection
    {
        return Noticia::query()->publicadas()->limit(self::CANTIDAD)->get();
    }
}; ?>

<div>
    {{-- Sin noticias publicadas, la sección no se muestra. --}}
    @if ($this->noticias->isNotEmpty())
        <section aria-labelledby="ultimas-noticias-titulo" class="flex flex-col gap-4" data-test="ultimas-noticias">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="ultimas-noticias-titulo" class="text-xl font-semibold text-neutral-900">{{ __('Últimas noticias') }}</h2>
                <a href="{{ route('noticias') }}" class="text-sm font-medium text-[#16a34a] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16a34a]" wire:navigate>
                    {{ __('Ver todas') }}
                </a>
            </div>

            @foreach ($this->noticias as $noticia)
                <x-tarjeta-noticia :noticia="$noticia" nivel-titulo="h3" wire:key="ultima-noticia-{{ $noticia->id }}" />
            @endforeach
        </section>
    @endif
</div>
