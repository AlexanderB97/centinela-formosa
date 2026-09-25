<?php

use Livewire\Livewire;

test('analizador page can be rendered without authentication', function () {
    $this->get(route('analizar'))
        ->assertOk()
        ->assertSee('Analizador de riesgo')
        ->assertSee('role="tablist"', false);
});

test('analizador shows the three tabs', function () {
    Livewire::test('pages::analizador')
        ->assertSee('Texto')
        ->assertSee('Link')
        ->assertSee('Foto de QR');
});

test('the qr file input is never bound to livewire', function () {
    $html = Livewire::test('pages::analizador')
        ->call('seleccionarTipo', 'qr')
        ->html();

    expect($html)->toContain('id="archivo-qr"');
    expect($html)->not->toMatch('/<input[^>]*id="archivo-qr"[^>]*wire:model/s');
});

test('switching tabs clears the previous content and result', function () {
    Livewire::test('pages::analizador')
        ->set('contenido', 'Hola, ¿cómo estás?')
        ->call('analizar')
        ->call('seleccionarTipo', 'link')
        ->assertSet('tipo', 'link')
        ->assertSet('contenido', '')
        ->assertSet('resultado', null);
});

test('risky keywords produce a riesgo result', function () {
    Livewire::test('pages::analizador')
        ->set('contenido', 'URGENTE: tu cuenta del Banco fue suspendida, verificá tu cuenta para cobrar el premio')
        ->call('analizar')
        ->assertHasNoErrors()
        ->assertSet('resultado.nivel', 'riesgo')
        ->assertSee('data-nivel="riesgo"', false)
        ->assertSee('Menciona una entidad bancaria o datos de cuenta.');
});

test('suspicious links produce a dudoso result', function () {
    Livewire::test('pages::analizador')
        ->set('contenido', 'Mirá estas fotos bit.ly/fotos-2024')
        ->call('analizar')
        ->assertSet('resultado.nivel', 'dudoso')
        ->assertSee('Usa un acortador de enlaces que oculta el destino real.');

    Livewire::test('pages::analizador')
        ->call('seleccionarTipo', 'link')
        ->set('contenido', 'http://mi-cuenta-segura-oficial.com/login')
        ->call('analizar')
        ->assertHasNoErrors()
        ->assertSet('resultado.nivel', 'dudoso');
});

test('neutral content produces a seguro result', function () {
    Livewire::test('pages::analizador')
        ->set('contenido', 'Hola, ¿venís a cenar el sábado?')
        ->call('analizar')
        ->assertSet('resultado.nivel', 'seguro')
        ->assertSee('data-nivel="seguro"', false);
});

test('mock result follows the backend contract shape', function () {
    $resultado = Livewire::test('pages::analizador')
        ->set('contenido', 'Ganaste un premio')
        ->call('analizar')
        ->get('resultado');

    expect($resultado)->toHaveKeys(['nivel', 'razones', 'explicacion', 'explicacion_generada_por_ia']);
    expect($resultado['nivel'])->toBeIn(['seguro', 'dudoso', 'riesgo']);
    expect($resultado['razones'])->toBeArray()->not->toBeEmpty();
    expect($resultado['explicacion'])->toBeString();
    expect($resultado['explicacion_generada_por_ia'])->toBeBool();
});

test('empty content shows a validation error', function () {
    Livewire::test('pages::analizador')
        ->set('contenido', '')
        ->call('analizar')
        ->assertHasErrors(['contenido' => 'required'])
        ->assertSee('Pegá el mensaje que querés analizar.')
        ->assertSet('resultado', null);
});

test('link tab requires a valid url', function () {
    Livewire::test('pages::analizador')
        ->call('seleccionarTipo', 'link')
        ->set('contenido', 'esto no es un link')
        ->call('analizar')
        ->assertHasErrors(['contenido' => 'url']);
});

test('qr tab asks for a photo when nothing was decoded', function () {
    Livewire::test('pages::analizador')
        ->call('seleccionarTipo', 'qr')
        ->call('analizar')
        ->assertHasErrors(['contenido' => 'required'])
        ->assertSee('Primero subí una foto con un código QR.');
});

test('analysis failure shows an error with a retry option', function () {
    Livewire::test('pages::analizador')
        ->set('contenido', 'simular-error')
        ->call('analizar')
        ->assertSet('error', true)
        ->assertSet('resultado', null)
        ->assertSet('contenido', 'simular-error')
        ->assertSee('No pudimos completar el análisis.')
        ->assertSee('Reintentar');
});

test('result offers a path to report', function () {
    Livewire::test('pages::analizador')
        ->set('contenido', 'Ganaste un sorteo')
        ->call('analizar')
        ->assertSee('data-test="reportar-button"', false)
        ->call('reportar')
        ->assertOk();
});
