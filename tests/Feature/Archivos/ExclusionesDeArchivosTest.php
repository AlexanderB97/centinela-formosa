<?php

use App\Enums\Barrio;
use App\Enums\Departamento;
use App\Enums\NivelRiesgo;
use App\Enums\TipoContenido;
use App\Models\Analisis;
use App\Models\Reporte;
use App\Models\UsuarioStaff;
use App\Services\EstadisticasImpacto;
use App\Services\MapaZonasAfectadas;
use App\Services\RankingConsultados;
use App\Services\RiskAnalyzer;
use Illuminate\Support\Collection;
use Livewire\Livewire;

function analisisDeArchivo(int $cantidad = 1, NivelRiesgo $nivel = NivelRiesgo::Riesgo, ?string $huella = null): Collection
{
    return Analisis::factory()->count($cantidad)->create([
        'tipo' => TipoContenido::Archivo,
        'contenido' => $huella ?? hash('sha256', 'archivo repetido'),
        'nivel' => $nivel,
    ]);
}

test('RiskAnalyzer refuses files: they are scanned only by hash', function () {
    expect(fn () => app(RiskAnalyzer::class)->analizar(TipoContenido::Archivo, hash('sha256', 'x')))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => app(RiskAnalyzer::class)->analizar('archivo', hash('sha256', 'x')))
        ->toThrow(InvalidArgumentException::class);
});

test('the public POST /analizar API does not accept the archivo type', function () {
    $this->postJson(route('analizar.store'), ['tipo' => 'archivo', 'contenido' => hash('sha256', 'x')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tipo');

    expect(Analisis::count())->toBe(0);
});

test('file scans never appear in the rankings', function () {
    analisisDeArchivo(6);

    expect(app(RankingConsultados::class)->obtener())->toBe(['texto' => [], 'link' => [], 'qr' => []]);
});

test('reports of file scans never count for the map', function () {
    foreach (analisisDeArchivo(6) as $analisis) {
        Reporte::factory()->create(['analisis_id' => $analisis->id, 'departamento' => Departamento::FormosaCapital, 'barrio' => Barrio::Centro]);
    }

    expect(app(MapaZonasAfectadas::class)->obtener())->toBe([]);

    Reporte::factory()->count(5)->create(['departamento' => Departamento::FormosaCapital, 'barrio' => Barrio::Centro]);

    expect(app(MapaZonasAfectadas::class)->obtener()[0]['total'])->toBe(5);
});

test('file scans count in /impacto like any other analysis', function () {
    analisisDeArchivo(2, NivelRiesgo::Riesgo);
    Analisis::factory()->create(['tipo' => TipoContenido::Texto, 'nivel' => NivelRiesgo::Seguro]);

    expect(app(EstadisticasImpacto::class)->obtener())->toMatchArray([
        'total_analisis' => 3,
        'distribucion_nivel' => ['seguro' => 1, 'dudoso' => 0, 'riesgo' => 2],
    ]);
});

test('the staff queue labels file scans and explains the hash', function () {
    $this->actingAs(UsuarioStaff::factory()->create(), 'staff');
    $huella = hash('sha256', 'archivo reportado');
    Reporte::factory()->create(['analisis_id' => analisisDeArchivo(1, NivelRiesgo::Dudoso, $huella)->first()->id]);

    Livewire::test('pages::staff.reportes')
        ->assertSee('Archivo')
        ->assertSee($huella)
        ->assertSee('El archivo nunca se subió ni se guardó')
        ->assertDontSee('El enlace se muestra como texto');
});
