<?php

use App\Enums\Barrio;
use App\Enums\Departamento;
use App\Models\Reporte;
use App\Services\MapaZonasAfectadas;
use Livewire\Livewire;

function reportesDelBarrio(Barrio $barrio, int $cantidad): void
{
    Reporte::factory()->count($cantidad)->create(['departamento' => Departamento::FormosaCapital, 'barrio' => $barrio]);
}

test('mapa page is reachable without authentication', function () {
    $this->assertGuest();

    $html = $this->get(route('mapa'))
        ->assertOk()
        ->assertSeeLivewire('pages::mapa')
        ->assertSee('Mapa de barrios afectados (Formosa Capital)')
        ->getContent();

    // The map script is loaded: compiled (build/assets/mapa-*.js) or from the Vite dev server (npm run dev).
    expect($html)->toMatch('~(assets/mapa-[\w-]+\.js|resources/js/mapa\.js)~');
});

test('public navbar links to mapa and marks it as current on that page', function () {
    $this->get(route('home'))
        ->assertSee('href="'.route('mapa').'"', false)
        ->assertDontSee('aria-current="page"', false);

    $this->get(route('mapa'))
        ->assertSee('aria-current="page"', false);
});

test('the zones reach the map and the accessible list', function () {
    reportesDelBarrio(Barrio::VillaDelCarmen, 6);

    Livewire::test('pages::mapa')
        ->assertSet('zonas.0.barrio', 'villa_del_carmen')
        ->assertSet('zonas.0.total', 6)
        ->assertSeeHtml('data-zona="villa_del_carmen"')
        ->assertSee('Villa del Carmen')
        ->assertSee('6 reportes')
        ->assertDontSeeHtml('data-test="mapa-vacio"');
});

test('with no zones the map still renders with an empty notice', function () {
    reportesDelBarrio(Barrio::Guadalupe, 4);

    Livewire::test('pages::mapa')
        ->assertSet('zonas', [])
        ->assertSeeHtml('data-test="mapa-vacio"')
        ->assertSeeHtml('x-ref="mapa"')
        ->assertSee('Ninguno por ahora.');
});

test('the approximate location warning is prominent, before the map', function () {
    $html = Livewire::test('pages::mapa')
        ->assertSeeHtml('data-test="aviso-precision"')
        ->assertSee('Ubicaciones aproximadas.')
        ->assertSee('no verificadas contra un catastro oficial')
        ->html();

    expect(strpos($html, 'data-test="aviso-precision"'))->toBeLessThan(strpos($html, 'x-ref="mapa"'));
});

test('the footnote explains reference points, the threshold and the base map', function () {
    Livewire::test('pages::mapa')
        ->assertSeeHtml('data-test="nota-precision"')
        ->assertSee('referencia aproximada del barrio')
        ->assertSee('al menos 5 reportes')
        ->assertSee('OpenStreetMap');
});

test('the map always receives the bounds computed from every neighborhood, with or without zones', function () {
    Livewire::test('pages::mapa')
        ->assertSet('zonas', [])
        ->assertSet('limites', MapaZonasAfectadas::limites())
        ->assertSeeHtml('-26.265')
        ->assertSeeHtml('-58.295');
});
