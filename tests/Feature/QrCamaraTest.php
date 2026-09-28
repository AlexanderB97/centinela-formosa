<?php

use Illuminate\Support\Str;
use Livewire\Livewire;

// The camera itself runs only in the browser (resources/js/qr.js) and has no JS test setup:
// these tests cover the markup the server renders for it. What reaches the server (tipo = qr and
// the decoded text) is the same as with an uploaded photo and is covered by AnalizadorTest.

function htmlDelPanelQr(): string
{
    return Livewire::test('pages::analizador')->call('seleccionarTipo', 'qr')->html();
}

test('the qr tab offers uploading a photo and using the camera, with upload as the default', function () {
    $html = htmlDelPanelQr();

    preg_match('~<button[^>]*data-test="modo-imagen"[^>]*>~s', $html, $subir);
    preg_match('~<button[^>]*data-test="modo-camara"[^>]*>~s', $html, $camara);

    expect($html)->toContain('Subir imagen')
        ->and($html)->toContain('Usar cámara')
        ->and($subir[0])->toContain('aria-pressed="true"')
        ->and($camara[0])->toContain('aria-pressed="false"')
        ->and($html)->toContain("modo: 'imagen'");
});

test('the camera is never opened on load: only after tapping "Usar cámara"', function () {
    $html = htmlDelPanelQr();

    // The only paths to abrirCamara() are the explicit buttons, never init().
    expect($html)->not->toMatch('/init\(\)\s*\{[^}]*abrirCamara/s')
        ->and($html)->toContain("if (modo === 'camara') this.abrirCamara();")
        ->and($html)->toContain("camara: ''");
});

test('the camera video plays inline, muted, and is protected from Livewire re-renders', function () {
    $html = htmlDelPanelQr();

    preg_match('~<video[^>]*>~s', $html, $video);
    preg_match('~<div[^>]*data-test="qr-camara"[^>]*>~s', $html, $bloque);

    expect($video[0])->toContain('playsinline')
        ->and($video[0])->toContain('muted')
        ->and($video[0])->toContain('x-ref="video"')
        ->and($video[0])->not->toContain('wire:model')
        ->and($video[0])->not->toContain('src=')
        ->and($bloque[0])->toContain('wire:ignore');
});

test('the camera stops on tab changes, hidden pages and when the panel is destroyed', function () {
    $html = Livewire::test('pages::analizador')->call('seleccionarTipo', 'qr')->html();

    expect($html)->toContain("\$dispatch('analizador-cambio-tab')")
        ->and($html)->toContain('x-on:analizador-cambio-tab.window="detenerCamara();')
        ->and($html)->toContain('x-on:visibilitychange.document="document.hidden && pausar()"')
        ->and($html)->toContain('x-on:pagehide.window="pausar()"')
        ->and($html)->toMatch('/destroy\(\)\s*\{\s*this\.detenerCamara\(\);/');
});

test('photo and camera share one path for a decoded QR; only the camera analyzes on its own', function () {
    $html = htmlDelPanelQr();
    $panelQr = Str::between($html, "modo: 'imagen',", 'destroy()');

    expect(substr_count($panelQr, 'this.$wire.contenido = texto;'))->toBe(1)
        ->and($html)->toContain('if (texto) this.usarTextoQr(texto);')
        ->and($html)->toContain('this.usarTextoQr(texto, { analizarYa: true });');
});

test('every camera problem has a clear message and keeps the upload option', function (string $codigo, string $mensaje) {
    $html = htmlDelPanelQr();

    expect($html)->toContain("x-show=\"camara === '{$codigo}'\"")
        ->and($html)->toContain($mensaje)
        ->and($html)->toContain("x-on:click=\"elegirModo('imagen')\"");
})->with([
    'not https' => ['inseguro', 'La cámara solo funciona si el sitio se abre con https.'],
    'no browser support' => ['sin-soporte', 'Tu navegador no permite usar la cámara acá.'],
    'permission denied' => ['permiso', 'No diste permiso para usar la cámara.'],
    'no camera' => ['sin-camara', 'No encontramos una cámara en este dispositivo.'],
    'camera busy' => ['ocupada', 'Otra app está usando la cámara.'],
    'other error' => ['error', 'No pudimos abrir la cámara.'],
    'time limit' => ['agotada', 'No encontramos un código QR en un minuto'],
]);

test('the qr script is loaded as a page asset', function () {
    // Built assets (assets/qr-*.js) or the Vite dev server (resources/js/qr.js) when npm run dev is running.
    expect($this->get(route('home'))->assertOk()->getContent())
        ->toMatch('~(assets/qr-[\w-]+\.js|resources/js/qr\.js)~');
});
