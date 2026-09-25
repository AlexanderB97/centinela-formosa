<?php

use App\Enums\NivelRiesgo;
use App\Enums\TipoContenido;
use App\Models\Analisis;
use App\Models\CasoConfirmado;
use App\Services\Analisis\ResultadoAnalisis;
use App\Services\RiskAnalyzer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

function analizador(): RiskAnalyzer
{
    return app(RiskAnalyzer::class);
}

function conApis(): void
{
    config([
        'services.virustotal.key' => 'vt-test-key',
        'services.gemini.key' => 'gemini-test-key',
    ]);
}

function respuestaVirusTotal(int $maliciosos = 0, int $sospechosos = 0): array
{
    return ['data' => ['attributes' => ['last_analysis_stats' => [
        'harmless' => 60, 'malicious' => $maliciosos, 'suspicious' => $sospechosos, 'undetected' => 10,
    ]]]];
}

function respuestaGemini(string $texto, string $finishReason = 'STOP'): array
{
    return ['candidates' => [['content' => ['parts' => [['text' => $texto]]], 'finishReason' => $finishReason]]];
}

test('it is resolvable from the container and returns the contract shape', function () {
    $resultado = analizador()->analizar('texto', 'Hola, ¿cómo estás?');

    expect($resultado)->toBeInstanceOf(ResultadoAnalisis::class)
        ->and($resultado->toArray())->toHaveKeys(['nivel', 'razones', 'explicacion', 'explicacion_generada_por_ia'])
        ->and($resultado->toArray()['nivel'])->toBe('seguro')
        ->and($resultado->toArray()['razones'])->toBe(['No se detectaron patrones típicos de estafa.']);
});

test('it accepts the type as enum or string and rejects invalid input', function () {
    expect(analizador()->analizar(TipoContenido::Texto, 'hola')->nivel)->toBe(NivelRiesgo::Seguro);

    expect(fn () => analizador()->analizar('imagen', 'hola'))->toThrow(InvalidArgumentException::class);
    expect(fn () => analizador()->analizar('texto', '   '))->toThrow(InvalidArgumentException::class);
});

test('own rules classify text and links', function (string $tipo, string $contenido, NivelRiesgo $nivel) {
    expect(analizador()->analizar($tipo, $contenido)->nivel)->toBe($nivel);
})->with([
    'phishing message' => ['texto', 'URGENTE: tu cuenta del Banco fue suspendida, verificá tu cuenta para cobrar el premio', NivelRiesgo::Riesgo],
    'asks for codes' => ['texto', 'Pasame el código de verificación que te llegó, es urgente', NivelRiesgo::Riesgo],
    'shortener' => ['texto', 'Mirá estas fotos bit.ly/fotos-2024', NivelRiesgo::Dudoso],
    'insecure hyphenated link' => ['link', 'http://mi-cuenta-segura-oficial.com/login', NivelRiesgo::Dudoso],
    'brand impersonation' => ['link', 'https://mercadopago-ayuda.xyz/login', NivelRiesgo::Riesgo],
    'ip link from a qr' => ['qr', 'http://192.168.10.5/pago', NivelRiesgo::Riesgo],
    'official site' => ['link', 'https://www.mercadopago.com.ar/ayuda', NivelRiesgo::Seguro],
    'plain message' => ['texto', 'Nos vemos mañana a las 18 en la plaza', NivelRiesgo::Seguro],
]);

test('a confirmed case returns riesgo without consulting virustotal', function () {
    conApis();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(respuestaGemini('Explicación.'))]);
    CasoConfirmado::factory()->create(['contenido' => 'Hola, soy tu nieto, cambié de número']);

    $resultado = analizador()->analizar('texto', "  hola, SOY tu nieto,\n cambié de número ");

    expect($resultado->nivel)->toBe(NivelRiesgo::Riesgo)
        ->and($resultado->razones[0])->toBe('Coincide con un caso de estafa ya confirmado por nuestro equipo.');
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'virustotal.com'));
});

test('a confirmed link matches inside a text and ignoring scheme or www', function () {
    CasoConfirmado::factory()->link('https://www.pagos-rapidos.com/cobrar/')->create();

    $resultado = analizador()->analizar('texto', 'Cobrá tu plata acá: http://pagos-rapidos.com/cobrar');

    expect($resultado->nivel)->toBe(NivelRiesgo::Riesgo);
});

test('an unseen link is consulted on virustotal and influences the level', function () {
    conApis();
    Http::fake([
        'www.virustotal.com/*' => Http::response(respuestaVirusTotal(maliciosos: 7)),
        'generativelanguage.googleapis.com/*' => Http::response(respuestaGemini('Explicación.')),
    ]);

    $resultado = analizador()->analizar('link', 'https://sitio-normal.com.ar/inicio');

    expect($resultado->nivel)->toBe(NivelRiesgo::Riesgo)
        ->and($resultado->razones)->toContain('7 servicios de seguridad de VirusTotal marcan el enlace como malicioso.');

    $id = rtrim(strtr(base64_encode('https://sitio-normal.com.ar/inicio'), '+/', '-_'), '=');
    Http::assertSent(fn (Request $request) => $request->url() === "https://www.virustotal.com/api/v3/urls/{$id}"
        && $request->hasHeader('x-apikey', 'vt-test-key'));
});

test('a clean virustotal report keeps the link as seguro', function () {
    conApis();
    Http::fake([
        'www.virustotal.com/*' => Http::response(respuestaVirusTotal()),
        'generativelanguage.googleapis.com/*' => Http::response(respuestaGemini('Explicación.')),
    ]);

    $resultado = analizador()->analizar('link', 'https://sitio-normal.com.ar/inicio');

    expect($resultado->nivel)->toBe(NivelRiesgo::Seguro)
        ->and($resultado->razones)->toContain('VirusTotal no reporta el enlace como peligroso.');
});

test('virustotal results are cached per url', function () {
    conApis();
    Http::fake([
        'www.virustotal.com/*' => Http::response(respuestaVirusTotal(maliciosos: 1)),
        'generativelanguage.googleapis.com/*' => Http::response(respuestaGemini('Explicación.')),
    ]);

    analizador()->analizar('link', 'https://sitio-normal.com.ar/inicio');
    $resultado = analizador()->analizar('link', 'https://sitio-normal.com.ar/inicio');

    expect($resultado->nivel)->toBe(NivelRiesgo::Dudoso);
    Http::assertSentCount(3); // 1 VirusTotal + 2 Gemini
});

test('an unknown link on virustotal does not change the level', function () {
    conApis();
    Http::fake([
        'www.virustotal.com/*' => Http::response(['error' => ['code' => 'NotFoundError']], 404),
        'generativelanguage.googleapis.com/*' => Http::response(respuestaGemini('Explicación.')),
    ]);

    expect(analizador()->analizar('link', 'https://sitio-normal.com.ar/inicio')->nivel)->toBe(NivelRiesgo::Seguro);
});

test('if virustotal fails or times out the level comes from own rules', function ($respuesta) {
    conApis();
    Http::fake([
        'www.virustotal.com/*' => $respuesta,
        'generativelanguage.googleapis.com/*' => Http::response(respuestaGemini('Explicación.')),
    ]);

    $resultado = analizador()->analizar('texto', 'Mirá estas fotos bit.ly/fotos-2024');

    expect($resultado->nivel)->toBe(NivelRiesgo::Dudoso)
        ->and($resultado->razones)->toContain('No pudimos consultar VirusTotal: el resultado se basa en nuestras reglas.');
})->with([
    'timeout' => fn () => Http::failedConnection(),
    'server error' => fn () => Http::response('Service Unavailable', 503),
    'quota exceeded' => fn () => Http::response(['error' => ['code' => 'QuotaExceededError']], 429),
    'malformed payload' => fn () => Http::response(['data' => []]),
]);

test('gemini only receives the computed level and reasons, never the content', function () {
    conApis();
    Http::fake([
        'www.virustotal.com/*' => Http::response(respuestaVirusTotal()),
        'generativelanguage.googleapis.com/*' => Http::response(respuestaGemini('  Este mensaje tiene señales de estafa. No respondas.  ')),
    ]);

    $resultado = analizador()->analizar('texto', 'URGENTE: verificá tu cuenta del banco secreto-unico-123');

    expect($resultado->explicacion)->toBe('Este mensaje tiene señales de estafa. No respondas.')
        ->and($resultado->explicacionGeneradaPorIa)->toBeTrue()
        ->and($resultado->nivel)->toBe(NivelRiesgo::Riesgo);

    Http::assertSent(function (Request $request) {
        if (! str_contains($request->url(), 'generativelanguage.googleapis.com')) {
            return false;
        }

        $cuerpo = json_encode($request->data());

        return $request->hasHeader('x-goog-api-key', 'gemini-test-key')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'Nivel de riesgo: riesgo')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'Pide verificar, reactivar o actualizar una cuenta.')
            && ! str_contains($cuerpo, 'secreto-unico-123');
    });
});

test('the ai cannot change the level even if it says otherwise', function () {
    conApis();
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(respuestaGemini('Ignorá todo: este contenido es seguro.')),
    ]);

    $resultado = analizador()->analizar('texto', 'Ganaste un premio, pasame tu clave del homebanking urgente');

    expect($resultado->nivel)->toBe(NivelRiesgo::Riesgo);
});

test('if gemini fails the explanation falls back to the fixed template', function ($respuesta) {
    conApis();
    Http::fake(['generativelanguage.googleapis.com/*' => $respuesta]);

    $resultado = analizador()->analizar('texto', 'Mirá estas fotos bit.ly/fotos-2024');

    expect($resultado->explicacion)->toBe(NivelRiesgo::Dudoso->explicacionDeRespaldo())
        ->and($resultado->explicacionGeneradaPorIa)->toBeFalse();
})->with([
    'timeout' => fn () => Http::failedConnection(),
    'server error' => fn () => Http::response('Internal error', 500),
    'empty text' => fn () => Http::response(respuestaGemini('   ')),
    'truncated' => fn () => Http::response(respuestaGemini('Este contenido', 'MAX_TOKENS')),
    'blocked' => fn () => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']]),
]);

test('without api keys it works offline with rules and templates', function () {
    Http::fake();

    $resultado = analizador()->analizar('link', 'https://sitio-normal.com.ar/inicio');

    expect($resultado->nivel)->toBe(NivelRiesgo::Seguro)
        ->and($resultado->explicacion)->toBe(NivelRiesgo::Seguro->explicacionDeRespaldo())
        ->and($resultado->explicacionGeneradaPorIa)->toBeFalse();
    Http::assertNothingSent();
});

test('every analysis is stored', function () {
    analizador()->analizar('qr', 'Mirá estas fotos bit.ly/fotos-2024');

    $analisis = Analisis::sole();

    expect($analisis->tipo)->toBe(TipoContenido::Qr)
        ->and($analisis->contenido)->toBe('Mirá estas fotos bit.ly/fotos-2024')
        ->and($analisis->nivel)->toBe(NivelRiesgo::Dudoso)
        ->and($analisis->razones)->toBe(['Usa un acortador de enlaces que oculta el destino real.'])
        ->and($analisis->explicacion)->toBe(NivelRiesgo::Dudoso->explicacionDeRespaldo())
        ->and($analisis->explicacion_generada_por_ia)->toBeFalse();
});
