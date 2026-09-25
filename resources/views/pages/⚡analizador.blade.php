<?php

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::publico')] #[Title('Analizador de riesgo')] class extends Component {
    public string $tipo = 'texto';
    public string $contenido = '';

    /**
     * Resultado con la forma exacta del contrato de POST /analizar:
     * { nivel: 'seguro'|'dudoso'|'riesgo', razones: string[], explicacion: string, explicacion_generada_por_ia: bool }
     *
     * @var array{nivel: string, razones: array<int, string>, explicacion: string, explicacion_generada_por_ia: bool}|null
     */
    public ?array $resultado = null;

    public bool $error = false;

    public function seleccionarTipo(string $tipo): void
    {
        if (! in_array($tipo, ['texto', 'link', 'qr'], true)) {
            return;
        }

        $this->tipo = $tipo;
        $this->reset('contenido', 'resultado', 'error');
        $this->resetValidation();
    }

    public function analizar(): void
    {
        $this->reset('resultado', 'error');

        // MOCK: reglas que imitan el 422 del contrato. Backend define las definitivas.
        $this->validate([
            'tipo' => ['required', Rule::in(['texto', 'link', 'qr'])],
            'contenido' => array_filter(['required', 'string', 'max:5000', $this->tipo === 'link' ? 'url' : null]),
        ], [
            'tipo.required' => 'Elegí qué querés analizar.',
            'tipo.in' => 'El tipo de contenido no es válido.',
            'contenido.required' => match ($this->tipo) {
                'link' => 'Pegá el link que querés analizar.',
                'qr' => 'Primero subí una foto con un código QR.',
                default => 'Pegá el mensaje que querés analizar.',
            },
            'contenido.max' => 'El contenido no puede superar los 5000 caracteres.',
            'contenido.url' => 'Ingresá un link completo, por ejemplo https://ejemplo.com.',
        ]);

        try {
            // TODO: backend reemplaza esta llamada por el servicio/acción real de análisis
            // (la misma lógica que usa POST /analizar), que devuelve este mismo array.
            $this->resultado = $this->analizarMock($this->contenido);
        } catch (Throwable $e) {
            report($e);
            $this->error = true;
        }
    }

    public function reportar(): void
    {
        // TODO: HU2.2 — conectar con el flujo de reporte (issue aparte). Por ahora no hace nada.
    }

    /**
     * MOCK: simula el análisis en memoria con palabras clave simples, solo para maquetar.
     * Backend lo reemplaza por el análisis real (VirusTotal/Gemini); nada de esto es definitivo.
     *
     * @return array{nivel: string, razones: array<int, string>, explicacion: string, explicacion_generada_por_ia: bool}
     */
    private function analizarMock(string $contenido): array
    {
        // MOCK: demora artificial para que el estado de carga se vea como con el backend real.
        if (! app()->runningUnitTests()) {
            usleep(random_int(800, 1500) * 1000);
        }

        $texto = Str::lower(Str::ascii($contenido));

        // MOCK: palabra clave para probar el estado de error + "Reintentar".
        if (str_contains($texto, 'simular-error')) {
            throw new RuntimeException('MOCK: error simulado del analizador.');
        }

        // MOCK: alterna el flag de forma determinística según el contenido.
        $porIa = crc32($contenido) % 2 === 0;

        $senalesDeRiesgo = [
            'Menciona una entidad bancaria o datos de cuenta.' => ['banco', 'cbu'],
            'Pide datos sensibles como claves o códigos.' => ['clave', 'contrasena', 'token', 'codigo de verificacion'],
            'Pide verificar o reactivar una cuenta.' => ['verificar cuenta', 'verificar tu cuenta', 'verifica tu cuenta', 'cuenta suspendida', 'cuenta bloqueada'],
            'Genera sensación de urgencia para que actúes rápido.' => ['urgente', 'inmediato', 'ultimo aviso'],
            'Promete un premio o un sorteo.' => ['premio', 'ganaste', 'sorteo'],
        ];

        $razones = [];

        foreach ($senalesDeRiesgo as $razon => $palabras) {
            if (Str::contains($texto, $palabras)) {
                $razones[] = $razon;
            }
        }

        if ($razones !== []) {
            return [
                'nivel' => 'riesgo',
                'razones' => array_slice($razones, 0, 3),
                'explicacion' => $porIa
                    ? 'Este contenido reúne varias señales típicas de estafa: busca generar confianza o apuro para que compartas datos o actúes sin pensar. No respondas ni abras los enlaces, y si dice venir de tu banco, comunicate por sus canales oficiales.'
                    : 'Detectamos señales asociadas a intentos de estafa. Te recomendamos no responder ni compartir datos personales.',
                'explicacion_generada_por_ia' => $porIa,
            ];
        }

        preg_match_all('~(?:https?://)?(?:www\.)?((?:[a-z0-9-]+\.)+[a-z]{2,}|\d{1,3}(?:\.\d{1,3}){3})~', $texto, $coincidencias);
        $dominios = $coincidencias[1];

        $acortadores = ['bit.ly', 'tinyurl.com', 'cutt.ly', 't.co', 'is.gd'];
        $razones = [];

        foreach ($dominios as $dominio) {
            if (in_array($dominio, $acortadores, true)) {
                $razones[] = 'Usa un acortador de enlaces que oculta el destino real.';
            }

            if (preg_match('~^\d{1,3}(?:\.\d{1,3}){3}$~', $dominio)) {
                $razones[] = 'El enlace usa una dirección IP en lugar de un nombre de dominio.';
            } elseif (preg_match_all('~\d~', $dominio) >= 4) {
                $razones[] = 'El dominio tiene muchos números, algo poco habitual en sitios legítimos.';
            }

            if (substr_count($dominio, '-') >= 3) {
                $razones[] = 'El dominio tiene muchos guiones, algo común en sitios que imitan a otros.';
            }
        }

        if (str_contains($texto, 'http://')) {
            $razones[] = 'El enlace no usa conexión segura (https).';
        }

        $razones = array_values(array_unique($razones));

        if ($razones !== []) {
            return [
                'nivel' => 'dudoso',
                'razones' => array_slice($razones, 0, 3),
                'explicacion' => $porIa
                    ? 'El contenido no muestra señales claras de estafa, pero el enlace tiene características que suelen usarse para ocultar el destino real. Antes de abrirlo, confirmá con quien te lo envió.'
                    : 'Encontramos algunas señales que requieren precaución. Verificá el origen antes de continuar.',
                'explicacion_generada_por_ia' => $porIa,
            ];
        }

        return [
            'nivel' => 'seguro',
            'razones' => [
                'No se detectaron patrones típicos de estafa.',
                $dominios === [] ? 'No contiene enlaces.' : 'Los enlaces no presentan señales sospechosas.',
            ],
            'explicacion' => $porIa
                ? 'No encontramos señales de riesgo en este contenido. Igual, nunca compartas claves ni códigos de verificación, aunque te los pidan por mensaje.'
                : 'No detectamos señales de riesgo. Mantené siempre la precaución con tus datos personales.',
            'explicacion_generada_por_ia' => $porIa,
        ];
    }
}; ?>

<div x-data="{ errorCliente: false }" x-on:analizador-error.window="errorCliente = true" class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold text-neutral-900 sm:text-3xl">{{ __('Analizador de riesgo') }}</h1>
        <p class="mt-2 text-neutral-600">
            {{ __('Pegá un mensaje, un link o subí la foto de un código QR y te decimos si parece una estafa.') }}
        </p>
    </div>

    <section class="rounded-xl bg-white p-4 shadow-sm sm:p-6">
        <div
            role="tablist"
            aria-label="{{ __('Qué querés analizar') }}"
            x-data="{
                mover(direccion) {
                    const tabs = [...this.$el.querySelectorAll('[role=tab]')];
                    const actual = tabs.indexOf(document.activeElement);
                    const siguiente = tabs[(actual + direccion + tabs.length) % tabs.length];
                    siguiente.focus();
                    siguiente.click();
                },
            }"
            x-on:keydown.arrow-right.prevent="mover(1)"
            x-on:keydown.arrow-left.prevent="mover(-1)"
            class="grid grid-cols-3 gap-1 rounded-lg bg-neutral-100 p-1"
        >
            @foreach (['texto' => __('Texto'), 'link' => __('Link'), 'qr' => __('Foto de QR')] as $valor => $etiqueta)
                <button
                    type="button"
                    role="tab"
                    id="tab-{{ $valor }}"
                    aria-controls="panel-analisis"
                    aria-selected="{{ $tipo === $valor ? 'true' : 'false' }}"
                    tabindex="{{ $tipo === $valor ? '0' : '-1' }}"
                    wire:click="seleccionarTipo('{{ $valor }}')"
                    x-on:click="errorCliente = false"
                    wire:loading.attr="disabled"
                    wire:target="analizar"
                    @class([
                        'rounded-md px-2 py-2 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16a34a] disabled:opacity-60',
                        'bg-[#12151a] text-white shadow-sm' => $tipo === $valor,
                        'text-neutral-700 hover:bg-neutral-200' => $tipo !== $valor,
                    ])
                >
                    {{ $etiqueta }}
                </button>
            @endforeach
        </div>

        <div role="tabpanel" id="panel-analisis" aria-labelledby="tab-{{ $tipo }}" class="mt-5">
            <form wire:submit="analizar" x-on:submit="errorCliente = false" novalidate class="flex flex-col gap-4">
                @php
                    $claseCampo = 'w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-neutral-900 placeholder:text-neutral-400 focus:border-[#16a34a] focus:outline-none focus:ring-2 focus:ring-[#16a34a]/40';
                @endphp

                @if ($tipo === 'texto')
                    <div class="flex flex-col gap-2">
                        <label for="contenido-texto" class="text-sm font-medium text-neutral-800">{{ __('Mensaje') }}</label>
                        <textarea
                            id="contenido-texto"
                            wire:model="contenido"
                            rows="5"
                            required
                            placeholder="{{ __('Pegá acá el mensaje de WhatsApp, SMS o email') }}"
                            @error('contenido') aria-invalid="true" aria-describedby="analisis-errores" @enderror
                            class="{{ $claseCampo }}"
                        ></textarea>
                    </div>
                @elseif ($tipo === 'link')
                    <div class="flex flex-col gap-2">
                        <label for="contenido-link" class="text-sm font-medium text-neutral-800">{{ __('Link') }}</label>
                        <input
                            id="contenido-link"
                            type="url"
                            inputmode="url"
                            wire:model="contenido"
                            required
                            autocomplete="off"
                            placeholder="https://ejemplo.com"
                            @error('contenido') aria-invalid="true" aria-describedby="analisis-errores" @enderror
                            class="{{ $claseCampo }}"
                        />
                    </div>
                @else
                    {{-- La imagen se decodifica en el navegador con jsQR: no tiene wire:model, así que nunca se sube. --}}
                    <div
                        x-data="{
                            estado: '',
                            async leer(evento) {
                                const archivo = evento.target.files[0];
                                this.$wire.contenido = '';

                                if (! archivo) {
                                    this.estado = '';
                                    return;
                                }

                                this.estado = 'leyendo';

                                try {
                                    const texto = await window.leerQrDeArchivo(archivo);
                                    this.estado = texto ? 'ok' : 'sin-qr';
                                    if (texto) this.$wire.contenido = texto;
                                } catch (e) {
                                    this.estado = 'invalido';
                                }
                            },
                        }"
                        class="flex flex-col gap-2"
                    >
                        <label for="archivo-qr" class="text-sm font-medium text-neutral-800">{{ __('Foto del código QR') }}</label>
                        <input
                            id="archivo-qr"
                            type="file"
                            accept="image/*"
                            x-on:change="leer($event)"
                            aria-describedby="qr-ayuda qr-estado"
                            @error('contenido') aria-invalid="true" @enderror
                            class="block w-full text-sm text-neutral-700 file:mr-3 file:rounded-md file:border-0 file:bg-[#12151a] file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-neutral-800"
                        />
                        <p id="qr-ayuda" class="text-xs text-neutral-500">
                            {{ __('La imagen se procesa en tu dispositivo: no se sube a ningún servidor.') }}
                        </p>

                        <div id="qr-estado" aria-live="polite" class="text-sm">
                            <p x-show="estado === 'leyendo'" x-cloak class="text-neutral-600">{{ __('Leyendo el código…') }}</p>
                            <p x-show="estado === 'sin-qr'" x-cloak class="text-red-700">{{ __('No encontramos un código QR en la imagen. Probá con una foto más nítida y de frente.') }}</p>
                            <p x-show="estado === 'invalido'" x-cloak class="text-red-700">{{ __('No pudimos leer la imagen. Probá con otra foto.') }}</p>
                        </div>

                        <div x-show="$wire.contenido" x-cloak class="rounded-md border border-neutral-200 bg-neutral-50 p-3">
                            <p class="text-xs font-medium text-neutral-600">{{ __('Contenido leído del QR') }}</p>
                            <p class="mt-1 break-all font-mono text-sm text-neutral-900" x-text="$wire.contenido"></p>
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div id="analisis-errores" role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="analizar"
                    data-test="analizar-button"
                    class="flex w-full items-center justify-center gap-2 rounded-md bg-[#22c55e] px-4 py-2.5 font-semibold text-[#12151a] transition hover:bg-[#16a34a] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16a34a] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70 sm:w-auto sm:self-end"
                >
                    <span wire:loading.remove wire:target="analizar">{{ __('Analizar') }}</span>
                    <span wire:loading.flex wire:target="analizar" class="items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4 animate-spin" fill="none" aria-hidden="true" focusable="false">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25" />
                            <path fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" class="opacity-75" />
                        </svg>
                        {{ __('Analizando…') }}
                    </span>
                </button>
            </form>
        </div>
    </section>

    <div aria-live="polite">
        <div wire:loading.flex wire:target="analizar" class="items-center gap-3 rounded-xl bg-white p-6 text-neutral-600 shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5 animate-spin text-[#16a34a]" fill="none" aria-hidden="true" focusable="false">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25" />
                <path fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" class="opacity-75" />
            </svg>
            {{ __('Analizando el contenido…') }}
        </div>

        <div wire:loading.remove wire:target="analizar">
            @php
                $bloqueError = 'rounded-xl border border-red-200 bg-white p-6 shadow-sm';
                $mensajeError = __('No pudimos completar el análisis. Puede ser un problema de conexión o del servicio. Tu contenido sigue acá, podés intentar de nuevo.');
                $claseReintentar = 'mt-4 rounded-md bg-[#12151a] px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16a34a] focus-visible:ring-offset-2';
            @endphp

            {{-- Error de red o HTTP (lo detecta el interceptor de abajo, sin respuesta del componente). --}}
            <div x-show="errorCliente" x-cloak role="alert" class="{{ $bloqueError }}">
                <p class="text-red-700">{{ $mensajeError }}</p>
                <button type="button" wire:click="analizar" x-on:click="errorCliente = false" class="{{ $claseReintentar }}">
                    {{ __('Reintentar') }}
                </button>
            </div>

            <div x-show="! errorCliente">
                @if ($error)
                    {{-- Error del análisis devuelto por el servidor. --}}
                    <div role="alert" class="{{ $bloqueError }}">
                        <p class="text-red-700">{{ $mensajeError }}</p>
                        <button type="button" wire:click="analizar" class="{{ $claseReintentar }}">
                            {{ __('Reintentar') }}
                        </button>
                    </div>
                @elseif ($resultado)
                    @php
                        $semaforo = [
                            'seguro' => [
                                'etiqueta' => __('Seguro'),
                                'resumen' => __('No encontramos señales de estafa.'),
                                'borde' => 'border-[#16a34a]',
                                'icono' => 'bg-[#16a34a] text-white',
                                'titulo' => 'text-green-800',
                            ],
                            'dudoso' => [
                                'etiqueta' => __('Dudoso'),
                                'resumen' => __('Tiene algunas señales que requieren precaución.'),
                                'borde' => 'border-yellow-500',
                                'icono' => 'bg-yellow-400 text-[#12151a]',
                                'titulo' => 'text-yellow-800',
                            ],
                            'riesgo' => [
                                'etiqueta' => __('Riesgo'),
                                'resumen' => __('Tiene señales claras de un intento de estafa.'),
                                'borde' => 'border-red-600',
                                'icono' => 'bg-red-600 text-white',
                                'titulo' => 'text-red-700',
                            ],
                        ];
                        $nivel = $resultado['nivel'];
                        $estilo = $semaforo[$nivel];
                    @endphp

                    <article data-nivel="{{ $nivel }}" aria-labelledby="resultado-titulo" class="rounded-xl border-l-8 bg-white p-6 shadow-sm {{ $estilo['borde'] }}">
                        <div class="flex items-start gap-4">
                            <span class="flex size-12 shrink-0 items-center justify-center rounded-full {{ $estilo['icono'] }}">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    @switch($nivel)
                                        @case('seguro')
                                            <path d="m5 12.5 4.5 4.5L19 7.5" />
                                            @break
                                        @case('dudoso')
                                            <path d="M12 7v6M12 17h.01" />
                                            @break
                                        @default
                                            <path d="M7 7l10 10M17 7 7 17" />
                                    @endswitch
                                </svg>
                            </span>
                            <div>
                                <p class="text-sm text-neutral-600">{{ __('Resultado') }}</p>
                                <h2 id="resultado-titulo" class="text-xl font-semibold {{ $estilo['titulo'] }}">{{ $estilo['etiqueta'] }}</h2>
                                <p class="text-neutral-700">{{ $estilo['resumen'] }}</p>
                            </div>
                        </div>

                        <h3 class="mt-6 text-sm font-semibold text-neutral-900">{{ __('Por qué') }}</h3>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-neutral-800">
                            @foreach ($resultado['razones'] as $razon)
                                <li>{{ $razon }}</li>
                            @endforeach
                        </ul>

                        <h3 class="mt-6 text-sm font-semibold text-neutral-900">{{ __('Explicación') }}</h3>
                        <p class="mt-2 text-neutral-800">{{ $resultado['explicacion'] }}</p>

                        <div class="mt-6 flex flex-col gap-3 border-t border-neutral-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-neutral-600">
                                {{ $nivel === 'seguro' ? __('¿Creés que igual es una estafa?') : __('¿Te llegó esto? Reportalo y ayudá a otros.') }}
                            </p>
                            {{-- TODO: HU2.2 — el reporte es otro issue; por ahora wire:click="reportar" no hace nada. --}}
                            <button
                                type="button"
                                wire:click="reportar"
                                data-test="reportar-button"
                                @class([
                                    'rounded-md px-4 py-2 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16a34a] focus-visible:ring-offset-2',
                                    'bg-[#12151a] text-white hover:bg-neutral-800' => $nivel !== 'seguro',
                                    'border border-neutral-300 text-neutral-800 hover:bg-neutral-50' => $nivel === 'seguro',
                                ])
                            >
                                {{ __('Reportar') }}
                            </button>
                        </div>
                    </article>
                @endif
            </div>
        </div>
    </div>
</div>

@assets
    @vite('resources/js/qr.js')
@endassets

@script
<script>
    // Si la request de "analizar" falla (respuesta 4xx/5xx o sin conexión), mostramos
    // un error propio con "Reintentar" en lugar del modal por defecto de Livewire.
    $wire.$intercept('analizar', ({ onError, onFailure }) => {
        onError(({ preventDefault }) => {
            preventDefault();
            window.dispatchEvent(new CustomEvent('analizador-error'));
        });

        onFailure(() => window.dispatchEvent(new CustomEvent('analizador-error')));
    });
</script>
@endscript
