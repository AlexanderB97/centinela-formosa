<?php

use App\Enums\EstadoNoticia;
use App\Models\Noticia;
use App\Services\PublicarNoticia;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::staff')] #[Title('Noticias')] class extends Component {
    /** Mensaje de la última acción y su tipo: 'exito' o 'conflicto' (422). */
    public ?string $aviso = null;
    public ?string $tipoAviso = null;

    /** Cambia en cada acción para que el aviso se vuelva a montar y reciba el foco. */
    #[Locked]
    public int $numeroAviso = 0;

    public function mount(): void
    {
        // Aviso que deja el formulario después de guardar.
        if (session()->has('aviso-noticia')) {
            $this->tipoAviso = 'exito';
            $this->aviso = session('aviso-noticia');
        }
    }

    /**
     * @return Collection<int, Noticia>
     */
    #[Computed]
    public function borradores(): Collection
    {
        return Noticia::query()
            ->with('autor:id,nombre')
            ->where('estado', EstadoNoticia::Borrador->value)
            ->latest('updated_at')
            ->latest('id')
            ->get();
    }

    /**
     * @return Collection<int, Noticia>
     */
    #[Computed]
    public function publicadas(): Collection
    {
        return Noticia::query()->with('autor:id,nombre')->publicadas()->get();
    }

    public function publicar(int $id): void
    {
        $this->cambiar($id, publicar: true);
    }

    public function despublicar(int $id): void
    {
        $this->cambiar($id, publicar: false);
    }

    private function cambiar(int $id, bool $publicar): void
    {
        // Defensa extra además de auth:staff (que también corre en cada request de Livewire).
        abort_unless(auth('staff')->check(), 403);

        $this->numeroAviso++;
        unset($this->borradores, $this->publicadas);

        $noticia = Noticia::find($id);

        if ($noticia === null) {
            $this->tipoAviso = 'conflicto';
            $this->aviso = "No encontramos la noticia #{$id}.";

            return;
        }

        try {
            $servicio = app(PublicarNoticia::class);
            $publicar ? $servicio->publicar($noticia) : $servicio->despublicar($noticia);
        } catch (ValidationException $e) {
            // 422: otra persona del staff ya la cambió (o doble clic). Aviso calmo, la lista ya está al día.
            $this->tipoAviso = 'conflicto';
            $this->aviso = collect($e->errors())->flatten()->first();

            return;
        }

        $this->tipoAviso = 'exito';
        $this->aviso = $publicar
            ? "Noticia #{$id} publicada: ya se ve en /noticias."
            : "Noticia #{$id} despublicada: volvió a borradores y ya no se ve en el sitio.";
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start gap-4">
        <div class="mr-auto">
            <h1 class="text-2xl font-semibold text-neutral-900">{{ __('Noticias') }}</h1>
            <p class="mt-1 text-sm text-neutral-600">
                {{ __('Toda noticia nueva queda como borrador. Solo se ve en el sitio cuando la publicás.') }}
            </p>
        </div>
        <a
            href="{{ route('staff.noticias.crear') }}"
            data-test="nueva-noticia"
            class="rounded-md bg-[#8a5a1f] px-4 py-2 text-sm font-medium text-white hover:bg-[#744b19] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f] focus-visible:ring-offset-2"
            wire:navigate
        >
            {{ __('Nueva noticia') }}
        </a>
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

    @foreach (['borradores' => __('Borradores'), 'publicadas' => __('Publicadas')] as $grupo => $tituloGrupo)
        @php $noticias = $grupo === 'borradores' ? $this->borradores : $this->publicadas; @endphp

        <section aria-labelledby="grupo-{{ $grupo }}" data-grupo="{{ $grupo }}" class="flex flex-col gap-3">
            <h2 id="grupo-{{ $grupo }}" class="text-lg font-medium text-neutral-900">
                {{ $tituloGrupo }} <span class="text-neutral-500">({{ $noticias->count() }})</span>
            </h2>

            @forelse ($noticias as $noticia)
                <article wire:key="noticia-{{ $noticia->id }}" data-noticia="{{ $noticia->id }}" class="flex flex-col gap-4 rounded-xl bg-white p-5 shadow-sm sm:flex-row sm:items-center">
                    @if ($noticia->urlImagen())
                        <img src="{{ $noticia->urlImagen() }}" alt="" class="h-20 w-32 shrink-0 rounded-md bg-neutral-100 object-cover" loading="lazy" />
                    @endif

                    <div class="min-w-0 flex-1">
                        <h3 class="break-words font-medium text-neutral-900">{{ $noticia->titulo }}</h3>
                        <p class="mt-1 text-xs text-neutral-500">
                            @if ($noticia->publicada_en)
                                {{ $noticia->estaPublicada() ? __('Publicada el') : __('Publicada por primera vez el') }}
                                {{ $noticia->fechaPublicacion() }}
                            @else
                                {{ __('Nunca publicada') }}
                            @endif
                            · {{ __('Autor') }}: {{ $noticia->autor?->nombre ?? __('Cuenta eliminada') }}
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        <a
                            href="{{ route('staff.noticias.editar', $noticia) }}"
                            aria-label="{{ __('Editar noticia') }} #{{ $noticia->id }}"
                            class="rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-800 hover:bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f] focus-visible:ring-offset-2"
                            wire:navigate
                        >
                            {{ __('Editar') }}
                        </a>
                        @if ($noticia->estaPublicada())
                            <button
                                type="button"
                                wire:click="despublicar({{ $noticia->id }})"
                                wire:loading.attr="disabled"
                                aria-label="{{ __('Despublicar noticia') }} #{{ $noticia->id }}"
                                data-test="despublicar-{{ $noticia->id }}"
                                class="rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-800 hover:bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f] focus-visible:ring-offset-2 disabled:opacity-60"
                            >
                                {{ __('Despublicar') }}
                            </button>
                        @else
                            <button
                                type="button"
                                wire:click="publicar({{ $noticia->id }})"
                                wire:loading.attr="disabled"
                                aria-label="{{ __('Publicar noticia') }} #{{ $noticia->id }}"
                                data-test="publicar-{{ $noticia->id }}"
                                class="rounded-md bg-[#8a5a1f] px-4 py-2 text-sm font-medium text-white hover:bg-[#744b19] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f] focus-visible:ring-offset-2 disabled:opacity-60"
                            >
                                {{ __('Publicar') }}
                            </button>
                        @endif
                    </div>
                </article>
            @empty
                <p class="rounded-xl bg-white p-6 text-sm text-neutral-500 shadow-sm" data-test="sin-{{ $grupo }}">
                    {{ $grupo === 'borradores' ? __('No hay borradores.') : __('Todavía no hay noticias publicadas.') }}
                </p>
            @endforelse
        </section>
    @endforeach
</div>
