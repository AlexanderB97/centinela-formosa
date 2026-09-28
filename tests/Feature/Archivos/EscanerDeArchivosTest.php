<?php

use App\Enums\NivelRiesgo;
use App\Enums\TipoContenido;
use App\Models\Analisis;
use App\Models\CasoConfirmado;
use App\Models\Reporte;
use App\Services\Analisis\ResultadoAnalisis;
use App\Services\Analisis\VirusTotalNoDisponible;
use App\Services\EscanerDeArchivos;
use App\Services\ModerarReporte;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.virustotal.key' => 'vt-test-key']);
});

function huellaDePrueba(string $contenido = 'contenido de un archivo de prueba'): string
{
    return hash('sha256', $contenido);
}

function virusTotalResponde(int $maliciosos = 0, int $sospechosos = 0): void
{
    Http::fake(['www.virustotal.com/api/v3/files/*' => Http::response(['data' => ['attributes' => ['last_analysis_stats' => [
        'harmless' => 0, 'malicious' => $maliciosos, 'suspicious' => $sospechosos, 'undetected' => 60,
    ]]]])]);
}

function escanear(string $huella): ResultadoAnalisis
{
    return app(EscanerDeArchivos::class)->analizar($huella);
}

test('only a lowercase 64 hex SHA-256 is accepted', function (string $huella) {
    expect(fn () => escanear($huella))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
})->with([
    'uppercase' => [strtoupper(hash('sha256', 'x'))],
    'too short' => [substr(hash('sha256', 'x'), 0, 63)],
    'not hex' => [str_repeat('z', 64)],
    'a file name' => ['comprobante.pdf'],
]);

test('only the hash is sent to VirusTotal, never the file', function () {
    virusTotalResponde();
    $huella = huellaDePrueba();

    escanear($huella);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $pedido) => $pedido->method() === 'GET'
        && $pedido->url() === "https://www.virustotal.com/api/v3/files/{$huella}"
        && $pedido->hasHeader('x-apikey', 'vt-test-key')
        && $pedido->body() === '');
});

test('the VirusTotal verdict maps to the risk level', function (int $maliciosos, int $sospechosos, NivelRiesgo $nivel, string $razon) {
    virusTotalResponde($maliciosos, $sospechosos);

    $resultado = escanear(huellaDePrueba());

    expect($resultado->nivel)->toBe($nivel)
        ->and($resultado->razones)->toBe([$razon])
        ->and($resultado->explicacion)->toBe(EscanerDeArchivos::explicacionDeRespaldo($nivel));
})->with([
    'several detections' => [5, 0, NivelRiesgo::Riesgo, '5 servicios de seguridad de VirusTotal marcan el archivo como malicioso.'],
    'one detection' => [1, 0, NivelRiesgo::Dudoso, 'Algún servicio de seguridad de VirusTotal marca el archivo como sospechoso.'],
    'only suspicious' => [0, 2, NivelRiesgo::Dudoso, 'Algún servicio de seguridad de VirusTotal marca el archivo como sospechoso.'],
    'known and clean' => [0, 0, NivelRiesgo::Seguro, 'VirusTotal conoce este archivo y ningún servicio de seguridad lo marca como peligroso.'],
]);

test('an unknown hash is dudoso with the fixed "not a guarantee" explanation, without asking the AI', function () {
    config(['services.gemini.key' => 'gemini-test-key']);
    Http::fake(['www.virustotal.com/api/v3/files/*' => Http::response(['error' => ['code' => 'NotFoundError']], 404)]);

    $resultado = escanear(huellaDePrueba());

    expect($resultado->nivel)->toBe(NivelRiesgo::Dudoso)
        ->and($resultado->razones)->toBe([EscanerDeArchivos::RAZON_SIN_COINCIDENCIAS])
        ->and($resultado->explicacion)->toBe(EscanerDeArchivos::EXPLICACION_SIN_COINCIDENCIAS)
        ->and($resultado->explicacionGeneradaPorIa)->toBeFalse()
        ->and($resultado->explicacion)->toContain('no garantiza que sea seguro');
    Http::assertNotSent(fn (Request $pedido) => str_contains($pedido->url(), 'generativelanguage'));
});

test('the VirusTotal answer is cached per hash', function () {
    virusTotalResponde(3);
    $huella = huellaDePrueba();

    escanear($huella);
    escanear($huella);

    Http::assertSentCount(1);
});

test('without VirusTotal there is no result and nothing is stored', function (Closure $preparar) {
    $preparar();

    expect(fn () => escanear(huellaDePrueba()))->toThrow(VirusTotalNoDisponible::class);
    expect(Analisis::count())->toBe(0);
})->with([
    'not configured' => [fn () => config(['services.virustotal.key' => null])],
    'server error' => [fn () => Http::fake(['*' => Http::response('Internal error', 500)])],
    'connection failure' => [fn () => Http::fake(['*' => Http::failedConnection()])],
    'unexpected payload' => [fn () => Http::fake(['*' => Http::response(['data' => []])])],
]);

test('a quota rejection (429) is logged apart and gives no result', function () {
    Log::spy();
    Http::fake(['*' => Http::response(['error' => ['code' => 'QuotaExceededError']], 429)]);

    expect(fn () => escanear(huellaDePrueba()))->toThrow(VirusTotalNoDisponible::class);

    Log::shouldHaveReceived('warning')->with('VirusTotal rechazó la consulta por cuota (429).');
});

test('only the hash is stored, as the contenido of a file analysis', function () {
    virusTotalResponde(2);
    $huella = huellaDePrueba();

    $resultado = escanear($huella);
    $analisis = Analisis::sole();

    expect($resultado->analisisId)->toBe($analisis->id)
        ->and($analisis->tipo)->toBe(TipoContenido::Archivo)
        ->and($analisis->contenido)->toBe($huella)
        ->and($analisis->nivel)->toBe(NivelRiesgo::Riesgo);
});

test('a hash confirmed by staff is riesgo without consulting VirusTotal', function () {
    $huella = huellaDePrueba();
    CasoConfirmado::factory()->create(['tipo' => TipoContenido::Archivo, 'contenido' => $huella]);

    $resultado = escanear($huella);

    expect($resultado->nivel)->toBe(NivelRiesgo::Riesgo)
        ->and($resultado->razones)->toBe([EscanerDeArchivos::RAZON_CASO_CONFIRMADO]);
    Http::assertNothingSent();
});

test('confirming the report of a file scan makes the same file riesgo next time', function () {
    Http::fake(['www.virustotal.com/api/v3/files/*' => Http::response([], 404)]);
    $huella = huellaDePrueba('un pdf que en realidad es una estafa');

    $primero = escanear($huella);
    $reporte = Reporte::factory()->create(['analisis_id' => $primero->analisisId]);

    app(ModerarReporte::class)->confirmar($reporte);

    expect(CasoConfirmado::sole()->tipo)->toBe(TipoContenido::Archivo)
        ->and(CasoConfirmado::sole()->contenido)->toBe($huella)
        ->and(escanear($huella)->nivel)->toBe(NivelRiesgo::Riesgo);
});
