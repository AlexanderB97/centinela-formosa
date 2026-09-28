<?php

use App\Models\Noticia;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::publico')] class extends Component {
    #[Locked]
    public Noticia $noticia;

    public function mount(Noticia $noticia): void
    {
        // Un borrador no existe para el público: 404, igual que un ID que no existe.
        abort_unless($noticia->estaPublicada(), 404);

        $this->noticia = $noticia;
    }

    public function render()
    {
        return $this->view()->title($this->noticia->titulo);
    }
}; ?>

<article class="flex flex-col gap-6">
    <div>
        <a href="{{ route('noticias') }}" class="text-sm font-medium text-[#16a34a] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16a34a]" wire:navigate>
            ← {{ __('Todas las noticias') }}
        </a>
        <p class="mt-4 text-xs font-medium uppercase tracking-wide text-[#16a34a]">
            <time datetime="{{ $noticia->publicada_en?->toIso8601String() }}">{{ $noticia->fechaPublicacion() }}</time>
        </p>
        <h1 class="mt-1 break-words text-2xl font-semibold text-neutral-900 sm:text-3xl">{{ $noticia->titulo }}</h1>
    </div>

    @if ($noticia->urlImagen())
        {{-- Decorativa: el título ya describe la noticia. --}}
        <img src="{{ $noticia->urlImagen() }}" alt="" class="aspect-video w-full rounded-xl bg-neutral-100 object-cover shadow-sm" />
    @endif

    {{-- Texto plano escapado, párrafo por párrafo: ni HTML ni links clickeables. --}}
    <div class="flex flex-col gap-4 rounded-xl bg-white p-5 text-neutral-800 shadow-sm sm:p-8" data-test="cuerpo-noticia">
        @foreach ($noticia->parrafos() as $parrafo)
            <p class="whitespace-pre-line break-words leading-relaxed">{{ $parrafo }}</p>
        @endforeach
    </div>
</article>
