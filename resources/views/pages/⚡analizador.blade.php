<?php

use App\Http\Requests\ReportarRequest;
use App\Models\Analisis;
use App\Services\ReportarAnalisis;
use App\Services\RiskAnalyzer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::publico')] #[Title('Analizador de riesgo')] class extends Component {
    public string $tipo = 'texto';
    public string $contenido = '';

    /**
     * Resultado de RiskAnalyzer, con la forma del contrato de POST /analizar:
     * { nivel: 'seguro'|'dudoso'|'riesgo', razones: string[], explicacion: string, explicacion_generada_por_ia: bool, analisis_id: int|null }
     * `analisis_id` es null si backend no pudo guardar el análisis; en ese caso no se puede reportar.
     *
     * @var array{nivel: string, razones: array<int, string>, explicacion: string, explicacion_generada_por_ia: bool, analisis_id: int|null}|null
     */
    #[Locked]
    public ?array $resultado = null;

    public bool $error = false;

    // DEPRECATED: solo la usa reportarMock(); la deduplicación real está en la tabla `reportes`. Se deja como referencia.
    /**
     * MOCK: IDs ya reportados en esta visita, para simular el 422 de "ya reportado".
     * Backend lo reemplaza por POST /reportar real y define la política de deduplicación.
     *
     * @var array<int, int>
     */
    #[Locked]
    public array $analisisReportados = [];

    public bool $reporteAbierto = false;
    public string $comentario = '';

    /** Mensaje del último reporte y su tipo: 'exito' (201), 'aviso' (422) o 'error' (falla del envío). */
    public ?string $avisoReporte = null;
    public ?string $tipoAviso = null;

    public function seleccionarTipo(string $tipo): void
    {
        if (! in_array($tipo, ['texto', 'link', 'qr'], true)) {
            return;
        }

        $this->tipo = $tipo;
        $this->reset('contenido', 'resultado', 'error', 'reporteAbierto', 'comentario', 'avisoReporte', 'tipoAviso');
        $this->resetValidation();
    }

    public function analizar(): void
    {
        $this->reset('resultado', 'error', 'reporteAbierto', 'comentario', 'avisoReporte', 'tipoAviso');

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
            // Misma lógica que POST /analizar; incluye el analisis_id real de la fila guardada.
            $this->resultado = app(RiskAnalyzer::class)->analizar($this->tipo, $this->contenido)->toArray();
        } catch (Throwable $e) {
            report($e);
            $this->error = true;
        }
    }

    public function abrirReporte(): void
    {
        if ($this->resultado === null || $this->resultado['analisis_id'] === null) {
            return;
        }

        $this->reporteAbierto = true;
        $this->reset('comentario', 'avisoReporte', 'tipoAviso');
        $this->resetValidation('comentario');
    }

    public function cerrarReporte(): void
    {
        $this->reset('reporteAbierto', 'comentario', 'avisoReporte', 'tipoAviso');
        $this->resetValidation('comentario');
    }

    public function reportar(): void
    {
        if ($this->resultado === null) {
            return;
        }

        $reglas = ReportarRequest::reglas();
        $mensajes = ReportarRequest::mensajes();

        // Error del comentario: se muestra debajo del campo, como cualquier error de validación.
        $this->validate(['comentario' => $reglas['comentario']], $mensajes);

        try {
            // Misma validación y lógica que POST /reportar, sin pasar por HTTP. Sin datos del visitante.
            Validator::make(['analisis_id' => $this->resultado['analisis_id']], ['analisis_id' => $reglas['analisis_id']], $mensajes)->validate();

            app(ReportarAnalisis::class)->registrar($this->resultado['analisis_id'], $this->comentario);
        } catch (ValidationException $e) {
            // 422 del contrato (análisis inexistente o ya reportado): aviso calmo, no un error.
            $this->tipoAviso = 'aviso';
            $this->avisoReporte = collect($e->errors())->flatten()->first();
            $this->reset('reporteAbierto', 'comentario');

            return;
        } catch (Throwable $e) {
            report($e);
            $this->tipoAviso = 'error';
            $this->avisoReporte = null;

            return;
        }

        $this->tipoAviso = 'exito';
        $this->avisoReporte = ReportarAnalisis::MENSAJE_EXITO;
        $this->reset('reporteAbierto', 'comentario');
    }

    // DEPRECATED: ya no se usa, ReportarAnalisis real lo reemplaza. Se deja como referencia.
    /**
     * MOCK: simula POST /reportar en memoria. Backend lo reemplaza por el endpoint real.
     * Devuelve [status, mensaje] imitando el 201 { mensaje } y los 422 del contrato.
     *
     * @return array{0: int, 1: string}
     */
    private function reportarMock(int $analisisId, ?string $comentario): array
    {
        // MOCK: demora artificial más corta que la del análisis.
        if (! app()->runningUnitTests()) {
            usleep(random_int(300, 600) * 1000);
        }

        // MOCK: palabra clave en el comentario para probar la falla del envío.
        if ($comentario !== null && str_contains(Str::lower($comentario), 'simular-error')) {
            throw new RuntimeException('MOCK: error simulado del reporte.');
        }

        // MOCK: 422 por analisis_id inexistente, ahora contra la tabla real `analisis`.
        if (! Analisis::whereKey($analisisId)->exists()) {
            return [422, 'No encontramos este análisis. Probá analizarlo de nuevo.'];
        }

        // MOCK: 422 por "ya reportado". Decisión de UI solo para el mock; la política real la define backend.
        if (in_array($analisisId, $this->analisisReportados, true)) {
            return [422, 'Ya reportaste este análisis, gracias.'];
        }

        // MOCK: el reporte no se persiste; el comentario se descarta.
        $this->analisisReportados[] = $analisisId;

        return [201, '¡Gracias! Tu reporte fue enviado de forma anónima.'];
    }

    // DEPRECATED: ya no se usa, RiskAnalyzer real lo reemplaza. Se deja como referencia.
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

    @php
        // Íconos SVG inline (trazos de 24x24), sin librerías externas.
        $iconos = [
            'texto' => '<path d="M21 12a8 8 0 0 1-11.6 7.14L4 20l1.1-4.2A8 8 0 1 1 21 12Z" />',
            'link' => '<path d="M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1" /><path d="M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1" />',
            'qr' => '<rect x="4" y="4" width="6" height="6" rx="1" /><rect x="14" y="4" width="6" height="6" rx="1" /><rect x="4" y="14" width="6" height="6" rx="1" /><path d="M14 14h2v2M20 14v2M14 20h6M18 18v2" />',
            'documento' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z" /><path d="M14 3v5h5M9 13h6M9 17h4" />',
            'camara' => '<path d="M4 8h3l2-3h6l2 3h3v11H4V8Z" /><circle cx="12" cy="13" r="3.5" />',
        ];

        // Ejemplos inventados a partir de patrones reales de estafa; se cargan en el campo, no se analizan solos.
        $ejemplos = [
            'texto' => [
                ['titulo' => __('Falso aviso del banco'), 'contenido' => 'URGENTE: tu cuenta bancaria fue suspendida. Para reactivarla respondé este mensaje con tu número de tarjeta y tu clave antes de las 18 hs.'],
                ['titulo' => __('Premio que nunca ganaste'), 'contenido' => '¡Felicitaciones! Ganaste un premio en el sorteo aniversario. Para cobrarlo confirmá tus datos en bit.ly/premio-cobro'],
                ['titulo' => __('Familiar con número nuevo'), 'contenido' => 'Hola, soy tu hijo, cambié de número. Necesito que me transfieras hoy mismo, es urgente, después te explico.'],
            ],
            'link' => [
                ['titulo' => __('Link acortado que oculta el destino'), 'contenido' => 'https://bit.ly/3xYz-beneficio'],
                ['titulo' => __('Imita a una marca conocida'), 'contenido' => 'https://mercadopago-ayuda.xyz/login'],
                ['titulo' => __('Sin conexión segura y lleno de guiones'), 'contenido' => 'http://mi-cuenta-segura-oficial.com/ingresar'],
            ],
        ];

        $zona = 'flex flex-col items-center gap-2 rounded-xl border-2 border-dashed border-neutral-300 bg-neutral-50 px-4 py-6 text-center transition focus-within:border-[#16a34a] sm:px-8 sm:py-8';
        $iconoZona = 'mb-1 flex size-14 items-center justify-center rounded-full bg-[#16a34a]/10 text-[#16a34a]';
        $tituloZona = 'text-lg font-semibold text-neutral-900 sm:text-xl';
        $ayudaZona = 'text-sm text-neutral-500';
        $claseCampo = 'mt-3 w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-left text-neutral-900 placeholder:text-neutral-400 focus:border-[#16a34a] focus:outline-none focus:ring-2 focus:ring-[#16a34a]/40';
    @endphp

    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
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
            class="flex border-b border-neutral-200 px-2 sm:px-4"
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
                        'group -mb-px inline-flex flex-1 items-center justify-center gap-2 border-b-4 px-2 py-3 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#16a34a] disabled:opacity-60 sm:text-base',
                        'border-[#16a34a] text-[#12151a]' => $tipo === $valor,
                        'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-800' => $tipo !== $valor,
                    ])
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" @class([
                        'size-5 shrink-0',
                        'text-[#16a34a]' => $tipo === $valor,
                        'text-neutral-400 group-hover:text-neutral-600' => $tipo !== $valor,
                    ])>{!! $iconos[$valor] !!}</svg>
                    <span>{{ $etiqueta }}</span>
                </button>
            @endforeach
        </div>

        <div
            role="tabpanel"
            id="panel-analisis"
            aria-labelledby="tab-{{ $tipo }}"
            x-data="{
                ejemplosAbiertos: false,
                usar(texto, campo) {
                    this.$wire.contenido = texto;
                    this.ejemplosAbiertos = false;
                    this.$nextTick(() => document.getElementById(campo)?.focus());
                },
            }"
            class="p-4 sm:p-6"
        >
            <form wire:submit="analizar" x-on:submit="errorCliente = false" novalidate class="flex flex-col gap-4">
                @if ($tipo === 'texto')
                    <div class="{{ $zona }}">
                        <span class="{{ $iconoZona }}" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">{!! $iconos['documento'] !!}</svg>
                        </span>
                        <label for="contenido-texto" class="{{ $tituloZona }}">{{ __('Pegá el mensaje que te llegó') }}</label>
                        <p id="texto-ayuda" class="{{ $ayudaZona }}">{{ __('WhatsApp, SMS o email. Copialo completo, tal como lo recibiste.') }}</p>
                        <textarea
                            id="contenido-texto"
                            wire:model="contenido"
                            rows="5"
                            required
                            placeholder="{{ __('Pegá acá el mensaje de WhatsApp, SMS o email') }}"
                            aria-describedby="texto-ayuda @error('contenido') analisis-errores @enderror"
                            @error('contenido') aria-invalid="true" @enderror
                            class="{{ $claseCampo }}"
                        ></textarea>
                    </div>
                @elseif ($tipo === 'link')
                    <div class="{{ $zona }}">
                        <span class="{{ $iconoZona }}" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">{!! $iconos['link'] !!}</svg>
                        </span>
                        <label for="contenido-link" class="{{ $tituloZona }}">{{ __('Pegá el link que querés revisar') }}</label>
                        <p id="link-ayuda" class="{{ $ayudaZona }}">{{ __('No lo abras: pegalo acá y lo revisamos antes.') }}</p>
                        <input
                            id="contenido-link"
                            type="url"
                            inputmode="url"
                            wire:model="contenido"
                            required
                            autocomplete="off"
                            placeholder="https://ejemplo.com"
                            aria-describedby="link-ayuda @error('contenido') analisis-errores @enderror"
                            @error('contenido') aria-invalid="true" @enderror
                            class="{{ $claseCampo }}"
                        />
                    </div>
                @else
                    {{-- La imagen se decodifica en el navegador con jsQR: no tiene wire:model, así que nunca se sube. --}}
                    <div
                        x-data="{
                            estado: '',
                            arrastrando: false,
                            async leer(archivo) {
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
                        <label
                            for="archivo-qr"
                            x-on:dragover.prevent="arrastrando = true"
                            x-on:dragleave.prevent="arrastrando = false"
                            x-on:drop.prevent="arrastrando = false; leer($event.dataTransfer.files[0])"
                            :class="arrastrando && 'border-[#16a34a] bg-[#16a34a]/5'"
                            class="{{ $zona }} cursor-pointer hover:border-[#16a34a] focus-within:ring-2 focus-within:ring-[#16a34a]/40"
                        >
                            <span class="{{ $iconoZona }}" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">{!! $iconos['camara'] !!}</svg>
                            </span>
                            <span id="qr-titulo" class="{{ $tituloZona }}">{{ __('Subí la foto del código QR') }}</span>
                            <span class="{{ $ayudaZona }}">{{ __('Arrastrala acá o tocá para elegirla. En el celular también podés sacarla con la cámara.') }}</span>
                            <span class="mt-2 inline-flex rounded-md bg-[#12151a] px-4 py-2 text-sm font-medium text-white" aria-hidden="true">{{ __('Elegir foto') }}</span>
                            <input
                                id="archivo-qr"
                                type="file"
                                accept="image/*"
                                x-on:change="leer($event.target.files[0])"
                                aria-labelledby="qr-titulo"
                                aria-describedby="qr-ayuda qr-estado"
                                @error('contenido') aria-invalid="true" @enderror
                                class="sr-only"
                            />
                        </label>
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

                @if ($errors->hasAny(['tipo', 'contenido']))
                    <div id="analisis-errores" role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ $errors->first('contenido') ?: $errors->first('tipo') }}
                    </div>
                @endif

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                    @if ($tipo !== 'qr')
                        <button
                            type="button"
                            x-on:click="ejemplosAbiertos = ! ejemplosAbiertos"
                            :aria-expanded="ejemplosAbiertos.toString()"
                            aria-expanded="false"
                            aria-controls="ejemplos"
                            data-test="ver-ejemplos-button"
                            class="rounded-md border border-neutral-300 bg-white px-4 py-2.5 text-sm font-medium text-neutral-800 transition hover:border-[#16a34a] hover:text-[#15803d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16a34a] focus-visible:ring-offset-2"
                        >
                            {{ __('Ver ejemplos') }}
                        </button>
                    @endif

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="analizar"
                        data-test="analizar-button"
                        class="flex w-full items-center justify-center gap-2 rounded-md bg-[#22c55e] px-4 py-2.5 font-semibold text-[#12151a] transition hover:bg-[#16a34a] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16a34a] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70 sm:w-auto"
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
                </div>

                @if ($tipo !== 'qr')
                    <div id="ejemplos" x-show="ejemplosAbiertos" x-cloak class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                        <p class="text-sm font-semibold text-neutral-900">{{ __('Ejemplos de estafas comunes') }}</p>
                        <p class="mt-1 text-xs text-neutral-500">{{ __('Son ejemplos inventados a partir de patrones reales. Elegí uno para cargarlo y después tocá Analizar.') }}</p>

                        <ul class="mt-3 flex flex-col gap-2">
                            @foreach ($ejemplos[$tipo] as $ejemplo)
                                <li class="flex flex-col gap-2 rounded-md border border-neutral-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $ejemplo['titulo'] }}</p>
                                        <p class="mt-1 break-words font-mono text-sm text-neutral-800">{{ $ejemplo['contenido'] }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        x-on:click="usar(@js($ejemplo['contenido']), @js($tipo === 'link' ? 'contenido-link' : 'contenido-texto'))"
                                        class="shrink-0 self-start rounded-md px-3 py-1.5 text-sm font-medium text-[#15803d] hover:bg-[#16a34a]/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16a34a] sm:self-center"
                                    >
                                        {{ __('Usar este ejemplo') }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
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

                        {{-- Sin analisis_id (backend no pudo guardar el análisis) no hay nada que reportar. --}}
                        @if ($resultado['analisis_id'] !== null)
                        <div class="mt-6 border-t border-neutral-200 pt-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-sm text-neutral-600">
                                    {{ $nivel === 'seguro' ? __('¿Creés que igual es una estafa?') : __('¿Te llegó esto? Reportalo y ayudá a otros.') }}
                                </p>
                                <button
                                    type="button"
                                    id="reportar-boton"
                                    wire:click="{{ $reporteAbierto ? 'cerrarReporte' : 'abrirReporte' }}"
                                    aria-expanded="{{ $reporteAbierto ? 'true' : 'false' }}"
                                    aria-controls="seccion-reporte"
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

                            {{-- Resultado del último reporte (201 o 422): tono calmo, nunca en rojo. --}}
                            @if ($avisoReporte && in_array($tipoAviso, ['exito', 'aviso'], true))
                                <div
                                    role="status"
                                    tabindex="-1"
                                    x-init="$nextTick(() => $el.focus())"
                                    data-aviso="{{ $tipoAviso }}"
                                    @class([
                                        'mt-4 flex items-start gap-3 rounded-md border px-4 py-3 text-sm focus:outline-none',
                                        'border-green-200 bg-green-50 text-green-900' => $tipoAviso === 'exito',
                                        'border-sky-200 bg-sky-50 text-sky-900' => $tipoAviso === 'aviso',
                                    ])
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="mt-0.5 size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                        @if ($tipoAviso === 'exito')
                                            <path d="m5 12.5 4.5 4.5L19 7.5" />
                                        @else
                                            <circle cx="12" cy="12" r="9" />
                                            <path d="M12 11v5M12 8h.01" />
                                        @endif
                                    </svg>
                                    <p>{{ $avisoReporte }}</p>
                                </div>
                            @endif

                            @if ($reporteAbierto)
                                {{-- Reporte anónimo: el único campo es el comentario opcional. No pedir datos personales. --}}
                                <section
                                    id="seccion-reporte"
                                    aria-labelledby="reporte-titulo"
                                    x-data="{
                                        errorRed: false,
                                        cerrar() {
                                            this.$wire.cerrarReporte().then(() => document.getElementById('reportar-boton')?.focus());
                                        },
                                    }"
                                    x-init="$nextTick(() => $refs.comentario.focus())"
                                    x-on:reporte-error.window="errorRed = true"
                                    x-on:keydown.escape.prevent.stop="cerrar()"
                                    class="mt-4 rounded-lg border border-neutral-200 bg-neutral-50 p-4"
                                >
                                    <h3 id="reporte-titulo" class="font-medium text-neutral-900">{{ __('Reportar este contenido') }}</h3>
                                    <p class="mt-1 text-sm text-neutral-600">
                                        {{ __('El reporte es anónimo: no te pedimos ningún dato tuyo.') }}
                                    </p>

                                    <form wire:submit="reportar" x-on:submit="errorRed = false" class="mt-3 flex flex-col gap-3">
                                        <div class="flex flex-col gap-2">
                                            <label for="comentario-reporte" class="text-sm font-medium text-neutral-800">{{ __('Comentario (opcional)') }}</label>
                                            <textarea
                                                id="comentario-reporte"
                                                x-ref="comentario"
                                                wire:model="comentario"
                                                rows="3"
                                                maxlength="500"
                                                placeholder="{{ __('Por ejemplo: me llegó por WhatsApp de un número desconocido') }}"
                                                aria-describedby="comentario-ayuda @error('comentario') comentario-error @enderror"
                                                @error('comentario') aria-invalid="true" @enderror
                                                class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-neutral-900 placeholder:text-neutral-400 focus:border-[#16a34a] focus:outline-none focus:ring-2 focus:ring-[#16a34a]/40"
                                            ></textarea>
                                            <p id="comentario-ayuda" class="text-xs text-neutral-500">
                                                {{ __('No incluyas datos personales. Máximo 500 caracteres.') }}
                                            </p>
                                            @error('comentario')
                                                <p id="comentario-error" class="text-sm text-red-700">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- Falla del envío (servidor o red): calmo, el comentario se conserva para reintentar. --}}
                                        <div
                                            x-show="errorRed || @js($tipoAviso === 'error')"
                                            @if ($tipoAviso !== 'error') x-cloak @endif
                                            role="status"
                                            data-aviso="error"
                                            class="rounded-md border border-neutral-200 bg-white px-3 py-2 text-sm text-neutral-800"
                                        >
                                            {{ __('No pudimos enviar el reporte. Tu comentario sigue acá, probá de nuevo en un momento.') }}
                                        </div>

                                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                            <button
                                                type="button"
                                                x-on:click="cerrar()"
                                                class="rounded-md border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-800 hover:bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16a34a] focus-visible:ring-offset-2"
                                            >
                                                {{ __('Cancelar') }}
                                            </button>
                                            <button
                                                type="submit"
                                                wire:loading.attr="disabled"
                                                wire:target="reportar"
                                                data-test="enviar-reporte-button"
                                                class="rounded-md bg-[#22c55e] px-4 py-2 text-sm font-semibold text-[#12151a] hover:bg-[#16a34a] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16a34a] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
                                            >
                                                <span wire:loading.remove wire:target="reportar">{{ __('Enviar reporte') }}</span>
                                                <span wire:loading wire:target="reportar">{{ __('Enviando…') }}</span>
                                            </button>
                                        </div>
                                    </form>
                                </section>
                            @endif
                        </div>
                        @endif
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

    // Lo mismo para el envío del reporte, pero con un aviso calmo dentro de la sección.
    $wire.$intercept('reportar', ({ onError, onFailure }) => {
        onError(({ preventDefault }) => {
            preventDefault();
            window.dispatchEvent(new CustomEvent('reporte-error'));
        });

        onFailure(() => window.dispatchEvent(new CustomEvent('reporte-error')));
    });
</script>
@endscript
