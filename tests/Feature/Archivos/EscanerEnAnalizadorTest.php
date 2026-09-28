<?php

use App\Enums\TipoContenido;
use App\Models\Analisis;
use App\Models\Reporte;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.virustotal.key' => 'vt-test-key']);
});

function tabArchivo()
{
    return Livewire::test('pages::analizador')->call('seleccionarTipo', 'archivo');
}

function escanearEnAnalizador(string $huella)
{
    return tabArchivo()->set('contenido', $huella)->call('analizar');
}

test('the analyzer offers a fourth tab to scan a file', function () {
    Livewire::test('pages::analizador')
        ->assertSeeHtml('id="tab-archivo"')
        ->assertSee('Archivo');

    tabArchivo()
        ->assertSet('tipo', 'archivo')
        ->assertSee('Elegí el archivo que te mandaron')
        ->assertSee('calculamos su huella (SHA-256) en tu dispositivo')
        ->assertSeeHtml('accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"')
        ->assertDontSeeHtml('data-test="ver-ejemplos-button"');
});

test('the file input is never bound to Livewire, so the file is never sent', function () {
    $html = tabArchivo()->html();

    preg_match('~<input[^>]*id="archivo-escaner"[^>]*>~s', $html, $input);

    expect($input)->not->toBeEmpty()
        ->and($input[0])->not->toContain('wire:model')
        ->and($input[0])->not->toContain('name=');
});

test('only a valid hash is accepted', function (string $contenido, string $mensaje) {
    tabArchivo()
        ->set('contenido', $contenido)
        ->call('analizar')
        ->assertHasErrors(['contenido'])
        ->assertSee($mensaje)
        ->assertSet('resultado', null);

    Http::assertNothingSent();
})->with([
    'no file chosen' => ['', 'Primero elegí el archivo que querés revisar.'],
    'not a hash' => ['comprobante.pdf', 'No pudimos calcular la huella del archivo.'],
]);

test('an unknown file shows "Sin coincidencias conocidas" and the not-a-guarantee explanation', function () {
    Http::fake(['www.virustotal.com/api/v3/files/*' => Http::response([], 404)]);

    escanearEnAnalizador(hash('sha256', 'archivo nuevo'))
        ->assertHasNoErrors()
        ->assertSet('resultado.nivel', 'dudoso')
        ->assertSet('resultado.sin_coincidencias', true)
        ->assertSee('Sin coincidencias conocidas')
        ->assertSee('Esto no garantiza que sea seguro')
        ->assertSeeHtml('data-test="reportar-button"');
});

test('a known malicious file shows the risk level', function () {
    Http::fake(['www.virustotal.com/api/v3/files/*' => Http::response(['data' => ['attributes' => ['last_analysis_stats' => ['malicious' => 12, 'suspicious' => 0]]]])]);

    escanearEnAnalizador(hash('sha256', 'malware conocido'))
        ->assertSet('resultado.nivel', 'riesgo')
        ->assertSet('resultado.sin_coincidencias', false)
        ->assertSee('Riesgo')
        ->assertSee('12 servicios de seguridad de VirusTotal marcan el archivo como malicioso.')
        ->assertDontSee('Sin coincidencias conocidas');
});

test('if VirusTotal cannot be consulted, a retry message is shown and nothing is stored', function () {
    Http::fake(['*' => Http::response([], 429)]);

    escanearEnAnalizador(hash('sha256', 'archivo'))
        ->assertSet('error', true)
        ->assertSet('resultado', null)
        ->assertSee('No pudimos completar el análisis.')
        ->assertSee('Reintentar');

    expect(Analisis::count())->toBe(0);
});

test('file scans are limited to four per minute per IP', function () {
    Http::fake(['www.virustotal.com/api/v3/files/*' => Http::response([], 404)]);

    $componente = tabArchivo();

    foreach (range(1, 4) as $numero) {
        $componente->set('contenido', hash('sha256', "archivo {$numero}"))->call('analizar')->assertHasNoErrors();
    }

    $componente->set('contenido', hash('sha256', 'archivo 5'))
        ->call('analizar')
        ->assertHasErrors(['contenido'])
        ->assertSee('Hiciste muchas consultas de archivos seguidas.')
        ->assertSet('resultado', null);

    Http::assertSentCount(4);
    expect(Analisis::count())->toBe(4);
});

test('a scanned file can be reported like any other analysis', function () {
    Http::fake(['www.virustotal.com/api/v3/files/*' => Http::response([], 404)]);

    escanearEnAnalizador(hash('sha256', 'archivo sospechoso'))
        ->call('abrirReporte')
        ->set('comentario', 'Me llegó por mail como factura')
        ->call('reportar')
        ->assertSet('tipoAviso', 'exito');

    expect(Reporte::sole()->analisis->tipo)->toBe(TipoContenido::Archivo);
});
