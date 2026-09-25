<?php

use App\Enums\NivelRiesgo;
use App\Models\Analisis;
use App\Models\CasoConfirmado;
use App\Models\Reporte;
use App\Services\EstadisticasImpacto;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

test('the numbers match exactly the rows in analisis, reportes and casos_confirmados', function () {
    $seguros = Analisis::factory()->count(6)->create(['nivel' => NivelRiesgo::Seguro]);
    Analisis::factory()->count(3)->create(['nivel' => NivelRiesgo::Dudoso]);
    Analisis::factory()->count(2)->create(['nivel' => NivelRiesgo::Riesgo]);

    // Reports of existing analyses, so the factory does not create extra ones.
    $seguros->take(4)->each(fn (Analisis $analisis) => Reporte::factory()->for($analisis)->create());
    CasoConfirmado::factory()->count(2)->create();

    expect(app(EstadisticasImpacto::class)->obtener())->toBe([
        'total_analisis' => 11,
        'total_reportes' => 4,
        'casos_confirmados' => 2,
        'distribucion_nivel' => ['seguro' => 6, 'dudoso' => 3, 'riesgo' => 2],
    ]);
});

test('the distribution adds up to the total and a level without analyses is 0', function () {
    Analisis::factory()->count(5)->create(['nivel' => NivelRiesgo::Seguro]);
    Analisis::factory()->count(2)->create(['nivel' => NivelRiesgo::Riesgo]);

    $estadisticas = app(EstadisticasImpacto::class)->obtener();

    expect(array_sum($estadisticas['distribucion_nivel']))->toBe($estadisticas['total_analisis'])
        ->and($estadisticas['distribucion_nivel'])->toBe(['seguro' => 5, 'dudoso' => 0, 'riesgo' => 2]);
});

test('with an empty database every count is 0', function () {
    expect(app(EstadisticasImpacto::class)->obtener())->toBe([
        'total_analisis' => 0,
        'total_reportes' => 0,
        'casos_confirmados' => 0,
        'distribucion_nivel' => ['seguro' => 0, 'dudoso' => 0, 'riesgo' => 0],
    ]);
});

test('it only runs aggregate queries and never loads rows into memory', function () {
    Analisis::factory()->count(30)->create(['contenido' => 'Contenido que nunca debe leerse']);
    Reporte::factory()->for(Analisis::first())->create(['comentario' => 'Comentario que nunca debe leerse']);
    CasoConfirmado::factory()->create();

    $consultas = [];
    DB::listen(function (QueryExecuted $consulta) use (&$consultas) {
        $consultas[] = strtolower($consulta->sql);
    });

    $estadisticas = app(EstadisticasImpacto::class)->obtener();

    // One GROUP BY over analisis (the total is its sum) plus COUNT(*) of reportes and casos_confirmados:
    // a fixed number of queries, whatever the number of rows.
    expect($consultas)->toHaveCount(3);

    foreach ($consultas as $sql) {
        expect($sql)->toContain('count(*)')
            ->not->toMatch('/select\s+\*/')
            ->not->toContain('contenido')
            ->not->toContain('comentario');
    }

    expect(json_encode($estadisticas))->not->toContain('nunca debe leerse');
});
