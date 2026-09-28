@props(['noticia', 'nivelTitulo' => 'h2'])

{{-- Tarjeta pública de una noticia publicada. Todo se muestra escapado: el cuerpo es texto plano. --}}
<article data-noticia="{{ $noticia->id }}" class="relative flex flex-col overflow-hidden rounded-xl bg-white shadow-sm transition focus-within:ring-2 focus-within:ring-[#16a34a] hover:shadow-md sm:flex-row">
    @if ($noticia->urlImagen())
        {{-- Decorativa: el título ya describe la noticia. --}}
        <img src="{{ $noticia->urlImagen() }}" alt="" loading="lazy" class="aspect-video w-full bg-neutral-100 object-cover sm:aspect-auto sm:w-48 sm:shrink-0" />
    @endif

    <div class="flex min-w-0 flex-1 flex-col gap-2 p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-[#16a34a]">
            <time datetime="{{ $noticia->publicada_en?->toIso8601String() }}">{{ $noticia->fechaPublicacion() }}</time>
        </p>
        <{{ $nivelTitulo }} class="break-words text-lg font-semibold text-neutral-900">
            <a href="{{ route('noticias.show', $noticia) }}" class="after:absolute after:inset-0 focus:outline-none" wire:navigate>{{ $noticia->titulo }}</a>
        </{{ $nivelTitulo }}>
        <p class="break-words text-sm text-neutral-600">{{ $noticia->extracto() }}</p>
    </div>
</article>
