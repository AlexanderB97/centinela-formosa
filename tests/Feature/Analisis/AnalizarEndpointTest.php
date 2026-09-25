<?php

use App\Models\Analisis;
use App\Models\CasoConfirmado;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('guests can analyze content through the api', function () {
    $this->postJson(route('analizar.store'), [
        'tipo' => 'texto',
        'contenido' => 'URGENTE: tu cuenta del Banco fue suspendida, verificá tu cuenta',
    ])
        ->assertOk()
        ->assertExactJsonStructure(['nivel', 'razones', 'explicacion', 'explicacion_generada_por_ia'])
        ->assertJson(['nivel' => 'riesgo', 'explicacion_generada_por_ia' => false]);

    expect(Analisis::count())->toBe(1);
});

test('validation errors are json even without an accept header', function () {
    $this->post('/analizar', ['tipo' => 'link', 'contenido' => 'no es un link'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('contenido');
});

test('a confirmed case returns riesgo through the api', function () {
    CasoConfirmado::factory()->link('https://premios-formosa.top/reclamar')->create();

    $this->postJson(route('analizar.store'), ['tipo' => 'qr', 'contenido' => 'https://premios-formosa.top/reclamar'])
        ->assertOk()
        ->assertJsonPath('nivel', 'riesgo');
});

test('it validates the request', function (array $datos, string $campo) {
    $this->postJson(route('analizar.store'), $datos)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($campo);

    expect(Analisis::count())->toBe(0);
})->with([
    'missing type' => [['contenido' => 'hola'], 'tipo'],
    'invalid type' => [['tipo' => 'imagen', 'contenido' => 'hola'], 'tipo'],
    'missing content' => [['tipo' => 'texto'], 'contenido'],
    'content too long' => [['tipo' => 'texto', 'contenido' => str_repeat('a', 5001)], 'contenido'],
    'link without url' => [['tipo' => 'link', 'contenido' => 'esto no es un link'], 'contenido'],
    'content not a string' => [['tipo' => 'texto', 'contenido' => ['a']], 'contenido'],
]);

test('external outages never produce a server error', function () {
    config(['services.virustotal.key' => 'k', 'services.gemini.key' => 'k']);
    Http::fake(['*' => Http::failedConnection()]);

    $this->postJson(route('analizar.store'), ['tipo' => 'link', 'contenido' => 'http://mi-cuenta-segura-oficial.com/login'])
        ->assertOk()
        ->assertJson(['nivel' => 'dudoso', 'explicacion_generada_por_ia' => false]);
});

test('the api is rate limited per ip', function () {
    foreach (range(1, 20) as $intento) {
        $this->postJson(route('analizar.store'), ['tipo' => 'texto', 'contenido' => 'hola'])->assertOk();
    }

    $this->postJson(route('analizar.store'), ['tipo' => 'texto', 'contenido' => 'hola'])->assertTooManyRequests();
});
