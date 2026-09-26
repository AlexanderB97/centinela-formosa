<?php

use App\Enums\NivelRiesgo;
use App\Enums\TipoContenido;
use App\Models\Analisis;
use App\Models\CasoConfirmado;
use Livewire\Livewire;

function repetido(TipoContenido $tipo, string $contenido, int $veces, NivelRiesgo $nivel = NivelRiesgo::Riesgo): void
{
    Analisis::factory()->count($veces)->create(['tipo' => $tipo, 'contenido' => $contenido, 'nivel' => $nivel]);
}

test('rankings page is reachable without authentication', function () {
    $this->assertGuest();

    $this->get(route('rankings'))
        ->assertOk()
        ->assertSeeLivewire('pages::rankings')
        ->assertSee('Lo más consultado');
});

test('public navbar links to rankings and marks it as current on that page', function () {
    $this->get(route('home'))
        ->assertSee('href="'.route('rankings').'"', false)
        ->assertDontSee('aria-current="page"', false);

    $this->get(route('rankings'))
        ->assertSee('aria-current="page"', false);
});

test('each category shows its own empty state', function () {
    repetido(TipoContenido::Link, 'https://premios-formosa.top/reclamar', 3);

    Livewire::test('pages::rankings')
        ->assertSeeHtml('data-test="ranking-texto-vacio"')
        ->assertDontSeeHtml('data-test="ranking-link-vacio"')
        ->assertSeeHtml('data-test="ranking-qr-vacio"')
        ->assertSee('Todavía no hay contenidos repetidos en esta categoría.');
});

test('items show the content, the count, the highest level and the confirmed badge', function () {
    repetido(TipoContenido::Texto, 'Ganaste un premio, reclamalo ya', 4, NivelRiesgo::Dudoso);
    repetido(TipoContenido::Texto, 'Hola, soy tu nieto, cambié de número', 3);
    CasoConfirmado::factory()->create(['contenido' => 'Hola, soy tu nieto, cambié de número']);

    Livewire::test('pages::rankings')
        ->assertSee('Ganaste un premio, reclamalo ya')
        ->assertSee('Analizado 4 veces')
        ->assertSee('Nivel: Dudoso')
        ->assertSee('Hola, soy tu nieto, cambié de número')
        ->assertSee('Analizado 3 veces')
        ->assertSee('Nivel: Riesgo')
        ->assertSeeHtml('data-test="confirmado"')
        ->assertSee('Confirmado como estafa');
});

test('ranked links are never clickable and never show their query string', function () {
    repetido(TipoContenido::Link, 'https://reset-clave.top/r?token=secreto123', 3);

    $html = Livewire::test('pages::rankings')->html();

    expect($html)->toContain('https://reset-clave.top/r')
        ->not->toContain('href="https://reset-clave.top')
        ->not->toContain('secreto123');
});

test('personal data and seguro-only content never reach the page', function () {
    repetido(TipoContenido::Texto, 'Llamá al 3704 123456 o escribí a juan@correo.com', 3);
    repetido(TipoContenido::Texto, 'Mensaje totalmente normal', 6, NivelRiesgo::Seguro);

    $html = Livewire::test('pages::rankings')->html();

    expect($html)->toContain('Llamá al [teléfono] o escribí a [email]')
        ->not->toContain('3704 123456')
        ->not->toContain('juan@correo.com')
        ->not->toContain('Mensaje totalmente normal');
});
