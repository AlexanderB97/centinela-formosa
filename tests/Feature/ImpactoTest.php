<?php

use Livewire\Livewire;

test('impacto page is reachable without authentication', function () {
    $this->assertGuest();

    $this->get(route('impacto'))
        ->assertOk()
        ->assertSeeLivewire('pages::impacto')
        ->assertSee('Impacto');
});

test('impacto shows the four aggregate counts', function () {
    Livewire::test('pages::impacto')
        ->assertSeeHtml('data-test="total-analisis"')
        ->assertSee('Análisis realizados')
        ->assertSee('1.243')
        ->assertSee('Reportes comunitarios')
        ->assertSee('87')
        ->assertSee('Casos confirmados')
        ->assertSee('52')
        ->assertSee('Distribución por nivel')
        ->assertSee('812')
        ->assertSee('289')
        ->assertSee('142');
});

test('mock follows the contract shape with aggregates only', function () {
    $estadisticas = Livewire::test('pages::impacto')->get('estadisticas');

    expect(array_keys($estadisticas))->toEqual(['total_analisis', 'total_reportes', 'casos_confirmados', 'distribucion_nivel']);
    expect(array_keys($estadisticas['distribucion_nivel']))->toEqual(['seguro', 'dudoso', 'riesgo']);
});

test('mock numbers are realistic and the distribution adds up to the total', function () {
    $estadisticas = Livewire::test('pages::impacto')->get('estadisticas');
    $distribucion = $estadisticas['distribucion_nivel'];

    expect(array_sum($distribucion))->toBe($estadisticas['total_analisis']);
    expect($estadisticas['total_reportes'])->toBeLessThan($estadisticas['total_analisis']);
    expect($estadisticas['casos_confirmados'])->toBeLessThan($estadisticas['total_reportes']);
    expect($distribucion['seguro'])->toBeGreaterThan($distribucion['dudoso']);
    expect($distribucion['dudoso'])->toBeGreaterThan($distribucion['riesgo']);
});

test('distribution bar is proportional and has a text alternative', function () {
    Livewire::test('pages::impacto')
        ->assertSeeHtml('data-segmento="seguro"')
        ->assertSeeHtml('style="width: 65.3258%"')
        ->assertSeeHtml('aria-label="65,3 % seguro, 23,3 % dudoso, 11,4 % riesgo"')
        ->assertSee('65,3 %');
});

test('empty state keeps the structure with zeros and a subtle message', function () {
    Livewire::test('pages::impacto', ['mockVacio' => true])
        ->assertSeeHtml('data-test="impacto-vacio"')
        ->assertSee('Todavía no hay estadísticas para mostrar.')
        ->assertSee('Análisis realizados')
        ->assertSee('Reportes comunitarios')
        ->assertSee('Casos confirmados')
        ->assertSee('0,0 %')
        ->assertSeeHtml('aria-label="Sin análisis todavía."')
        ->assertDontSeeHtml('data-segmento=')
        ->assertDontSee('NaN');
});

test('empty state message is not shown when there is data', function () {
    Livewire::test('pages::impacto')
        ->assertDontSeeHtml('data-test="impacto-vacio"');
});

test('public navbar links to impacto and marks it as current on that page', function () {
    $this->get(route('home'))
        ->assertSee('href="'.route('impacto').'"', false)
        ->assertDontSee('aria-current="page"', false);

    $this->get(route('impacto'))
        ->assertSee('aria-current="page"', false);
});
