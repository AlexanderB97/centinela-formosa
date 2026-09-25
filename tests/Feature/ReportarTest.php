<?php

use Illuminate\Support\Str;
use Livewire\Livewire;

function analizarAlgo(string $contenido = 'Ganaste un premio, urgente')
{
    return Livewire::test('pages::analizador')
        ->set('contenido', $contenido)
        ->call('analizar');
}

test('each new analysis gets an incremental mock analisis_id', function () {
    $component = analizarAlgo();
    expect($component->get('resultado.analisis_id'))->toBe(1);

    $component->call('analizar');
    expect($component->get('resultado.analisis_id'))->toBe(2);
});

test('failed analyses do not consume an analisis_id', function () {
    $component = Livewire::test('pages::analizador')
        ->set('contenido', 'simular-error')
        ->call('analizar')
        ->set('contenido', 'Hola')
        ->call('analizar');

    expect($component->get('resultado.analisis_id'))->toBe(1);
});

test('the report section opens from the result', function () {
    analizarAlgo()
        ->assertDontSee('id="seccion-reporte"', false)
        ->call('abrirReporte')
        ->assertSet('reporteAbierto', true)
        ->assertSee('id="seccion-reporte"', false)
        ->assertSee('aria-expanded="true"', false)
        ->assertSee('Comentario (opcional)')
        ->assertSee('data-test="enviar-reporte-button"', false);

    expect(analizarAlgo()->call('abrirReporte')->html())
        ->toMatch('~<button\s+type="submit"[^>]*data-test="enviar-reporte-button"~');
});

test('the report section can be closed without sending', function () {
    analizarAlgo()
        ->call('abrirReporte')
        ->set('comentario', 'algo')
        ->call('cerrarReporte')
        ->assertSet('reporteAbierto', false)
        ->assertSet('comentario', '')
        ->assertSet('analisisReportados', []);
});

test('sending a report with a comment shows a confirmation', function () {
    analizarAlgo()
        ->call('abrirReporte')
        ->set('comentario', 'Me llegó por WhatsApp de un número desconocido')
        ->call('reportar')
        ->assertHasNoErrors()
        ->assertSet('tipoAviso', 'exito')
        ->assertSet('reporteAbierto', false)
        ->assertSet('comentario', '')
        ->assertSet('analisisReportados', [1])
        ->assertSee('¡Gracias! Tu reporte fue enviado de forma anónima.')
        ->assertSee('data-aviso="exito"', false);
});

test('sending a report without a comment works', function () {
    analizarAlgo()
        ->call('abrirReporte')
        ->call('reportar')
        ->assertHasNoErrors()
        ->assertSet('tipoAviso', 'exito')
        ->assertSee('¡Gracias! Tu reporte fue enviado de forma anónima.');
});

test('reporting the same analysis twice shows a friendly already reported notice', function () {
    analizarAlgo()
        ->call('abrirReporte')
        ->call('reportar')
        ->call('abrirReporte')
        ->set('comentario', 'Otra vez')
        ->call('reportar')
        ->assertSet('tipoAviso', 'aviso')
        ->assertSet('analisisReportados', [1])
        ->assertSee('Ya reportaste este análisis, gracias.')
        ->assertSee('data-aviso="aviso"', false)
        ->assertDontSee('bg-red-50', false);
});

test('a new analysis can be reported after reporting a previous one', function () {
    analizarAlgo()
        ->call('abrirReporte')
        ->call('reportar')
        ->call('analizar')
        ->call('abrirReporte')
        ->call('reportar')
        ->assertSet('tipoAviso', 'exito')
        ->assertSet('analisisReportados', [1, 2]);
});

test('a failed report keeps the comment and shows a calm message', function () {
    analizarAlgo()
        ->call('abrirReporte')
        ->set('comentario', 'simular-error')
        ->call('reportar')
        ->assertSet('tipoAviso', 'error')
        ->assertSet('reporteAbierto', true)
        ->assertSet('comentario', 'simular-error')
        ->assertSet('analisisReportados', [])
        ->assertSee('x-show="errorRed || true"', false)
        ->assertSee('No pudimos enviar el reporte.')
        ->assertDontSee('bg-red-50', false);
});

test('comment longer than 500 characters is rejected', function () {
    analizarAlgo()
        ->call('abrirReporte')
        ->set('comentario', str_repeat('a', 501))
        ->call('reportar')
        ->assertHasErrors(['comentario' => 'max'])
        ->assertSet('analisisReportados', []);
});

test('the report form never asks for personal data', function () {
    $html = analizarAlgo()->call('abrirReporte')->html();

    preg_match('~<section\s+id="seccion-reporte".*?</section>~s', $html, $seccion);
    expect($seccion)->not->toBeEmpty();
    $seccion = $seccion[0];

    // El único campo del formulario es el comentario.
    expect(preg_match_all('~<(input|textarea|select)\b~', $seccion))->toBe(1);
    expect($seccion)->toContain('id="comentario-reporte"');

    foreach (['type="email"', 'type="tel"', 'nombre', 'name="email"', 'telefono', 'teléfono', 'apellido', 'dni'] as $prohibido) {
        expect(Str::lower($seccion))->not->toContain($prohibido);
    }
});
