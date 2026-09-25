<?php

use App\Models\CasoConfirmado;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Seeds real rows. Analyses go in one bulk insert, so the 1.243 rows of the old mock stay fast.
 */
function sembrarEstadisticas(int $seguro, int $dudoso, int $riesgo, int $reportes, int $casos): void
{
    $filas = [];

    foreach (['seguro' => $seguro, 'dudoso' => $dudoso, 'riesgo' => $riesgo] as $nivel => $cantidad) {
        for ($i = 1; $i <= $cantidad; $i++) {
            $filas[] = [
                'tipo' => 'texto',
                'contenido' => "Mensaje privado {$nivel} {$i}",
                'nivel' => $nivel,
                'razones' => '[]',
                'explicacion' => 'Explicación de prueba.',
                'explicacion_generada_por_ia' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }

    foreach (array_chunk($filas, 200) as $lote) {
        DB::table('analisis')->insert($lote);
    }

    $reportesNuevos = DB::table('analisis')->orderBy('id')->limit($reportes)->pluck('id')
        ->map(fn (int $id) => ['analisis_id' => $id, 'comentario' => 'Comentario privado', 'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now()]);

    DB::table('reportes')->insert($reportesNuevos->all());

    CasoConfirmado::factory()->count($casos)->create();
}

/**
 * The same numbers the frontend mock used, now as real rows.
 */
function sembrarNumerosDelMock(): void
{
    sembrarEstadisticas(seguro: 812, dudoso: 289, riesgo: 142, reportes: 87, casos: 52);
}

test('impacto page is reachable without authentication', function () {
    $this->assertGuest();

    $this->get(route('impacto'))
        ->assertOk()
        ->assertSeeLivewire('pages::impacto')
        ->assertSee('Impacto');
});

test('impacto shows the four aggregate counts from the database', function () {
    sembrarNumerosDelMock();

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

test('it follows the contract shape with aggregates only', function () {
    sembrarEstadisticas(seguro: 3, dudoso: 2, riesgo: 1, reportes: 2, casos: 1);

    $component = Livewire::test('pages::impacto');
    $estadisticas = $component->get('estadisticas');

    expect(array_keys($estadisticas))->toEqual(['total_analisis', 'total_reportes', 'casos_confirmados', 'distribucion_nivel']);
    expect(array_keys($estadisticas['distribucion_nivel']))->toEqual(['seguro', 'dudoso', 'riesgo']);
    expect($component->html())->not->toContain('Mensaje privado')
        ->not->toContain('Comentario privado');
});

test('the numbers match the tables and the distribution adds up to the total', function () {
    sembrarEstadisticas(seguro: 7, dudoso: 4, riesgo: 2, reportes: 3, casos: 2);

    $estadisticas = Livewire::test('pages::impacto')->get('estadisticas');

    expect($estadisticas)->toBe([
        'total_analisis' => 13,
        'total_reportes' => 3,
        'casos_confirmados' => 2,
        'distribucion_nivel' => ['seguro' => 7, 'dudoso' => 4, 'riesgo' => 2],
    ]);
    expect(array_sum($estadisticas['distribucion_nivel']))->toBe($estadisticas['total_analisis']);
});

test('distribution bar is proportional and has a text alternative', function () {
    sembrarNumerosDelMock();

    Livewire::test('pages::impacto')
        ->assertSeeHtml('data-segmento="seguro"')
        ->assertSeeHtml('style="width: 65.3258%"')
        ->assertSeeHtml('aria-label="65,3 % seguro, 23,3 % dudoso, 11,4 % riesgo"')
        ->assertSee('65,3 %');
});

test('empty state keeps the structure with zeros and a subtle message', function () {
    Livewire::test('pages::impacto')
        ->assertSet('estadisticas.total_analisis', 0)
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
    sembrarEstadisticas(seguro: 1, dudoso: 0, riesgo: 0, reportes: 0, casos: 0);

    Livewire::test('pages::impacto')
        ->assertDontSeeHtml('data-test="impacto-vacio"')
        ->assertDontSeeHtml('data-segmento="dudoso"')
        ->assertSeeHtml('data-segmento="seguro"');
});

test('public navbar links to impacto and marks it as current on that page', function () {
    $this->get(route('home'))
        ->assertSee('href="'.route('impacto').'"', false)
        ->assertDontSee('aria-current="page"', false);

    $this->get(route('impacto'))
        ->assertSee('aria-current="page"', false);
});
