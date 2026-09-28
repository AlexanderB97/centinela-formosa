<?php

use App\Models\Noticia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::publico')] #[Title('Noticias')] class extends Component {
    use WithPagination;

    public const POR_PAGINA = 10;

    /**
     * Solo noticias publicadas, de la más nueva a la más vieja. Los borradores nunca salen de acá.
     *
     * @return LengthAwarePaginator<int, Noticia>
     */
    #[Computed]
    public function noticias(): LengthAwarePaginator
    {
        return Noticia::query()->publicadas()->paginate(self::POR_PAGINA);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold text-neutral-900 sm:text-3xl">{{ __('Noticias') }}</h1>
        <p class="mt-2 text-neutral-600">
            {{ __('Novedades de Centinela Formosa y avisos sobre estafas que están circulando.') }}
        </p>
    </div>

    @if ($this->noticias->isEmpty())
        <div class="rounded-xl bg-white p-10 text-center shadow-sm" data-test="sin-noticias">
            <p class="text-lg font-medium text-neutral-900">{{ __('Todavía no hay noticias') }}</p>
            <p class="mt-1 text-sm text-neutral-600">{{ __('Cuando publiquemos novedades, las vas a ver acá.') }}</p>
        </div>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($this->noticias as $noticia)
                <x-tarjeta-noticia :noticia="$noticia" wire:key="noticia-{{ $noticia->id }}" />
            @endforeach
        </div>

        {{ $this->noticias->links(data: ['scrollTo' => false]) }}
    @endif
</div>
