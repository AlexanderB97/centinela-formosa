<?php

use App\Enums\Barrio;
use App\Enums\Departamento;
use App\Enums\EstadoReporte;
use App\Models\Reporte;
use App\Services\MapaZonasAfectadas;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

function reportesEn(?Barrio $barrio, int $cantidad, EstadoReporte $estado = EstadoReporte::Pendiente): void
{
    Reporte::factory()->count($cantidad)->create([
        'departamento' => $barrio ? Departamento::FormosaCapital : null,
        'barrio' => $barrio,
        'estado' => $estado,
    ]);
}

function zonas(): array
{
    return app(MapaZonasAfectadas::class)->obtener();
}

test('the threshold for neighborhoods is five reports', function () {
    expect(MapaZonasAfectadas::MINIMO_REPORTES)->toBe(5);
});

test('a neighborhood with fewer than five reports does not appear', function () {
    reportesEn(Barrio::SanMiguel, 4);

    expect(zonas())->toBe([]);
});

test('a neighborhood with five or more reports appears with its count and reference point', function () {
    reportesEn(Barrio::Guadalupe, 5);

    expect(zonas())->toBe([[
        'barrio' => 'guadalupe',
        'etiqueta' => 'Guadalupe',
        'lat' => -26.195,
        'lng' => -58.196,
        'total' => 5,
    ]]);
});

test('reports without a neighborhood do not count for any point, even from the capital', function () {
    reportesEn(null, 10);
    Reporte::factory()->count(5)->create(['departamento' => Departamento::FormosaCapital, 'barrio' => null]);
    reportesEn(Barrio::Lote111, 4);

    expect(zonas())->toBe([]);
});

test('discarded reports are not counted, pending and confirmed ones are', function () {
    reportesEn(Barrio::SanAntonio, 3);
    reportesEn(Barrio::SanAntonio, 2, EstadoReporte::Confirmado);
    reportesEn(Barrio::SanAntonio, 5, EstadoReporte::Descartado);
    reportesEn(Barrio::Malvinas, 5, EstadoReporte::Descartado);

    expect(zonas())->toHaveCount(1)
        ->and(zonas()[0]['barrio'])->toBe('san_antonio')
        ->and(zonas()[0]['total'])->toBe(5);
});

test('zones are ordered from the most reported neighborhood', function () {
    reportesEn(Barrio::SanMartin, 5);
    reportesEn(Barrio::OchoDeOctubre, 9);
    reportesEn(Barrio::BernardinoRivadaviaLote4, 7);
    reportesEn(Barrio::SanMiguel, 4);

    expect(array_column(zonas(), 'total', 'barrio'))->toBe([
        'ocho_de_octubre' => 9,
        'bernardino_rivadavia_lote_4' => 7,
        'san_martin' => 5,
    ]);
});

test('the reference points cover exactly the sixty neighborhoods', function () {
    expect(MapaZonasAfectadas::BARRIOS)->toHaveCount(60)
        ->and(array_keys(MapaZonasAfectadas::BARRIOS))->toEqualCanonicalizing(array_column(Barrio::cases(), 'value'));
});

test('the map bounds are derived from every neighborhood plus the margin', function () {
    [[$sur, $oeste], [$norte, $este]] = MapaZonasAfectadas::limites();

    // Extremes of the list: south Lote 111, west Villa del Carmen, north El Porvenir, east Bernardino Rivadavia (Lote 4).
    expect([$sur, $oeste, $norte, $este])->toBe([-26.265, -58.295, -26.1, -58.148]);

    foreach (MapaZonasAfectadas::BARRIOS as $barrio => $punto) {
        expect($punto['lat'])->toBeGreaterThan($sur)->toBeLessThan($norte, $barrio)
            ->and($punto['lng'])->toBeGreaterThan($oeste)->toBeLessThan($este, $barrio);
    }
});

test('it runs a single aggregate query and never loads individual reports', function () {
    reportesEn(Barrio::Guadalupe, 6);
    reportesEn(Barrio::SanMiguel, 5);
    reportesEn(null, 5);

    $consultas = [];
    DB::listen(function (QueryExecuted $consulta) use (&$consultas) {
        $consultas[] = strtolower($consulta->sql);
    });

    zonas();

    expect($consultas)->toHaveCount(1)
        ->and($consultas[0])->toContain('count(*)')
        ->toContain('group by')
        ->not->toMatch('/select\s+\*/')
        ->not->toContain('comentario');
});
