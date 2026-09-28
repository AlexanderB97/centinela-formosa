<?php

use App\Models\Noticia;
use App\Services\ImagenDeNoticia;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Layout('layouts::staff')] #[Title('Noticia')] class extends Component {
    use WithFileUploads;

    public const LARGO_MAXIMO_TITULO = 160;

    public const LARGO_MAXIMO_CUERPO = 20000;

    /** null al crear. */
    #[Locked]
    public ?int $noticiaId = null;

    #[Locked]
    public bool $publicada = false;

    /** URL de la portada que ya tiene la noticia (servida por ImagenNoticiaController). */
    #[Locked]
    public ?string $imagenActual = null;

    public string $titulo = '';
    public string $cuerpo = '';

    /**
     * Imagen nueva. Livewire la guarda primero en storage/app/private/livewire-tmp (fuera de public/)
     * y recién al guardar se reencodea a WebP con un nombre generado por el servidor.
     *
     * @var TemporaryUploadedFile|null
     */
    public $imagen = null;

    public bool $quitarImagen = false;

    public function mount(?Noticia $noticia = null): void
    {
        // En /staff/noticias/crear no hay parámetro y el contenedor puede dar un modelo vacío.
        if ($noticia?->exists) {
            $this->noticiaId = $noticia->id;
            $this->publicada = $noticia->estaPublicada();
            $this->imagenActual = $noticia->urlImagen();
            $this->titulo = $noticia->titulo;
            $this->cuerpo = $noticia->cuerpo;
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:'.self::LARGO_MAXIMO_TITULO],
            'cuerpo' => ['required', 'string', 'max:'.self::LARGO_MAXIMO_CUERPO],
            'imagen' => ImagenDeNoticia::reglas(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'titulo.required' => 'El título es obligatorio.',
            'titulo.max' => 'El título no puede superar los '.self::LARGO_MAXIMO_TITULO.' caracteres.',
            'cuerpo.required' => 'El texto de la noticia es obligatorio.',
            'cuerpo.max' => 'El texto no puede superar los '.number_format(self::LARGO_MAXIMO_CUERPO, 0, ',', '.').' caracteres.',
            'imagen.file' => 'La imagen tiene que ser JPG, PNG o WebP.',
            ...ImagenDeNoticia::mensajes(),
        ];
    }

    /** Validación inmediata apenas termina de subir, antes de guardar. */
    public function updatedImagen(): void
    {
        $this->quitarImagen = false;
        $this->validateOnly('imagen');
    }

    public function descartarImagen(): void
    {
        if ($this->imagen instanceof TemporaryUploadedFile) {
            $this->imagen->delete();
        }

        $this->reset('imagen');
        $this->resetValidation('imagen');
    }

    public function guardar(): void
    {
        // Defensa extra además de auth:staff (que también corre en cada request de Livewire).
        abort_unless(auth('staff')->check(), 403);

        $this->titulo = trim($this->titulo);
        $this->cuerpo = trim($this->cuerpo);

        $this->validate();

        $imagenes = app(ImagenDeNoticia::class);

        $noticia = $this->noticiaId === null
            ? new Noticia(['usuario_staff_id' => auth('staff')->id()])
            : Noticia::findOrFail($this->noticiaId);

        $anterior = $noticia->imagen_ruta;
        $nueva = $this->imagen instanceof TemporaryUploadedFile ? $imagenes->guardar($this->imagen) : null;

        try {
            $noticia->fill(['titulo' => $this->titulo, 'cuerpo' => $this->cuerpo]);

            if ($nueva !== null) {
                $noticia->imagen_ruta = $nueva;
            } elseif ($this->quitarImagen) {
                $noticia->imagen_ruta = null;
            }

            $noticia->save();
        } catch (Throwable $e) {
            // No dejar archivos huérfanos si la base falla.
            $imagenes->borrar($nueva);

            throw $e;
        }

        // La imagen vieja se borra recién cuando la base ya apunta a la nueva (o a ninguna).
        if ($anterior !== $noticia->imagen_ruta) {
            $imagenes->borrar($anterior);
        }

        if ($this->imagen instanceof TemporaryUploadedFile) {
            $this->imagen->delete();
        }

        session()->flash('aviso-noticia', match (true) {
            $this->noticiaId === null => "Noticia #{$noticia->id} guardada como borrador. Publicala cuando esté lista.",
            $noticia->estaPublicada() => "Noticia #{$noticia->id} actualizada. Los cambios ya se ven en el sitio.",
            default => "Borrador #{$noticia->id} actualizado.",
        });

        $this->redirectRoute('staff.noticias', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <a href="{{ route('staff.noticias') }}" class="text-sm font-medium text-[#8a5a1f] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f]" wire:navigate>
            ← {{ __('Volver a noticias') }}
        </a>
        <h1 class="mt-2 text-2xl font-semibold text-neutral-900">
            {{ $noticiaId === null ? __('Nueva noticia') : __('Editar noticia') }}
        </h1>
        @if ($publicada)
            <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-test="aviso-publicada">
                {{ __('Esta noticia está publicada: los cambios se ven en el sitio apenas guardes.') }}
            </p>
        @else
            <p class="mt-1 text-sm text-neutral-600">{{ __('Se guarda como borrador. Nadie la ve hasta que la publiques desde el listado.') }}</p>
        @endif
    </div>

    @php
        $claseInput = 'w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-neutral-900 placeholder:text-neutral-400 focus:border-[#8a5a1f] focus:outline-none focus:ring-2 focus:ring-[#8a5a1f]/40';
        $imagenNueva = $imagen instanceof TemporaryUploadedFile && ! $errors->has('imagen') && $imagen->isPreviewable();
    @endphp

    <form wire:submit="guardar" class="flex flex-col gap-5 rounded-xl bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-2">
            <label for="titulo" class="text-sm font-medium text-neutral-800">{{ __('Título') }}</label>
            <input
                id="titulo"
                type="text"
                wire:model="titulo"
                required
                maxlength="{{ $this::LARGO_MAXIMO_TITULO }}"
                autocomplete="off"
                @error('titulo') aria-invalid="true" aria-describedby="titulo-error" @enderror
                class="{{ $claseInput }}"
            />
            @error('titulo') <p id="titulo-error" class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-2">
            <label for="cuerpo" class="text-sm font-medium text-neutral-800">{{ __('Texto') }}</label>
            <textarea
                id="cuerpo"
                wire:model="cuerpo"
                required
                rows="12"
                maxlength="{{ $this::LARGO_MAXIMO_CUERPO }}"
                aria-describedby="cuerpo-ayuda @error('cuerpo') cuerpo-error @enderror"
                @error('cuerpo') aria-invalid="true" @enderror
                class="{{ $claseInput }}"
            ></textarea>
            <p id="cuerpo-ayuda" class="text-xs text-neutral-500">
                {{ __('Texto plano: dejá una línea en blanco entre párrafos. No se admite HTML ni formato, y los links no quedan clickeables.') }}
            </p>
            @error('cuerpo') <p id="cuerpo-error" class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        {{-- Portada opcional. El input no tiene wire:model: Alpine revisa tipo y tamaño en el navegador y recién ahí la sube. --}}
        <fieldset
            class="flex flex-col gap-3"
            x-data="{
                errorCliente: '',
                subiendo: false,
                progreso: 0,
                elegir(evento) {
                    const archivo = evento.target.files[0];
                    evento.target.value = '';
                    this.errorCliente = '';

                    if (! archivo) return;

                    if (! ['image/jpeg', 'image/png', 'image/webp'].includes(archivo.type)) {
                        this.errorCliente = @js(__('La imagen tiene que ser JPG, PNG o WebP.'));
                        return;
                    }

                    if (archivo.size > {{ ImagenDeNoticia::MAXIMO_KB * 1024 }}) {
                        this.errorCliente = @js(__('La imagen pesa más de 2 MB. Achicala o exportala como JPG o WebP y probá de nuevo.'));
                        return;
                    }

                    this.subiendo = true;
                    this.progreso = 0;
                    this.$wire.upload(
                        'imagen',
                        archivo,
                        () => { this.subiendo = false },
                        () => { this.subiendo = false; this.errorCliente = @js(__('No pudimos subir la imagen. Revisá que pese menos de 2 MB y probá de nuevo.')) },
                        (e) => { this.progreso = e.detail.progress },
                    );
                },
            }"
        >
            <legend class="text-sm font-medium text-neutral-800">{{ __('Imagen de portada (opcional)') }}</legend>
            <p id="imagen-ayuda" class="text-xs text-neutral-500">
                {{ __('JPG, PNG o WebP de hasta 2 MB. La convertimos a WebP y le borramos los datos ocultos de la foto (como la ubicación GPS).') }}
            </p>

            @if ($imagenNueva)
                <div class="flex flex-wrap items-end gap-3" data-test="vista-previa-nueva">
                    <img src="{{ $imagen->temporaryUrl() }}" alt="{{ __('Vista previa de la imagen nueva') }}" class="h-32 w-auto max-w-full rounded-md bg-neutral-100 object-cover" />
                    <button type="button" wire:click="descartarImagen" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm font-medium text-neutral-800 hover:bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f]">
                        {{ __('Descartar imagen nueva') }}
                    </button>
                </div>
            @elseif ($imagenActual && ! $quitarImagen)
                <div class="flex flex-wrap items-end gap-3" data-test="imagen-actual">
                    <img src="{{ $imagenActual }}" alt="{{ __('Imagen de portada actual') }}" class="h-32 w-auto max-w-full rounded-md bg-neutral-100 object-cover" />
                    <button type="button" wire:click="$set('quitarImagen', true)" data-test="quitar-imagen" class="rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm font-medium text-neutral-800 hover:bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f]">
                        {{ __('Quitar imagen') }}
                    </button>
                </div>
            @elseif ($imagenActual && $quitarImagen)
                <p class="text-sm text-neutral-600">
                    {{ __('La imagen actual se va a quitar al guardar.') }}
                    <button type="button" wire:click="$set('quitarImagen', false)" class="font-medium text-[#8a5a1f] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f]">{{ __('Deshacer') }}</button>
                </p>
            @endif

            <div>
                <label for="imagen-noticia" class="inline-flex cursor-pointer rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-800 hover:bg-neutral-100 focus-within:ring-2 focus-within:ring-[#8a5a1f]">
                    {{ $imagenNueva || ($imagenActual && ! $quitarImagen) ? __('Cambiar imagen') : __('Elegir imagen') }}
                    <input
                        id="imagen-noticia"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        x-on:change="elegir($event)"
                        aria-describedby="imagen-ayuda"
                        class="sr-only"
                    />
                </label>
            </div>

            <p x-show="subiendo" x-cloak role="status" class="text-sm text-neutral-600">
                {{ __('Subiendo imagen…') }} <span x-text="progreso + '%'"></span>
            </p>
            <p x-show="errorCliente" x-cloak x-text="errorCliente" role="alert" class="text-sm text-red-700"></p>
            @error('imagen') <p role="alert" class="text-sm text-red-700" data-test="imagen-error">{{ $message }}</p> @enderror
        </fieldset>

        <div class="flex justify-end border-t border-neutral-200 pt-4">
            <button
                type="submit"
                wire:loading.attr="disabled"
                data-test="guardar-noticia"
                class="rounded-md bg-[#8a5a1f] px-5 py-2.5 font-medium text-white transition hover:bg-[#744b19] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f] focus-visible:ring-offset-2 disabled:opacity-60"
            >
                {{ $noticiaId === null ? __('Guardar borrador') : __('Guardar cambios') }}
            </button>
        </div>
    </form>
</div>
